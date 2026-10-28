<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use App\Models\BulkFraudBatch;
use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use App\Models\User;
use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MerchantBulkFraudCheck
{
    /** The open batch page checks its own numbers. After this, the server takes over. */
    private const PAGE_OPEN_SECONDS = 30;

    /** Source of a number queued by "Check again as new"; it skips today's saved result. */
    private const RECHECK_SOURCE = 'recheck';

    public function __construct(
        private FraudCheckService $fraudCheckService,
        private FraudCheckCoordinator $coordinator,
        private CourierHistoryQuota $quota,
        private MerchantBulkWaitingLane $waitingLane,
        private CourierCallContext $context,
    ) {}

    public function start(User $merchant, string $name, string $phones): BulkFraudBatch
    {
        if (! $merchant->courier_history_enabled) {
            throw ValidationException::withMessages([
                'phones' => 'Courier history is not enabled for this account.',
            ]);
        }

        $parsed = $this->parse($phones);
        $max = (int) config('fraud_check.bulk_max_numbers', 1000);

        if ($parsed['entries'] === []) {
            throw ValidationException::withMessages([
                'phones' => 'Enter at least one phone number.',
            ]);
        }

        if (count($parsed['entries']) > $max) {
            throw ValidationException::withMessages([
                'phones' => 'A batch can include up to '.$max.' numbers after duplicates are removed.',
            ]);
        }

        $lock = Cache::lock('courier-history-merchant:'.$merchant->id, 30);

        try {
            $lock->block(8);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'phones' => 'Courier history is busy right now. Try again in a moment.',
            ]);
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

            throw ValidationException::withMessages([
                'phones' => 'Courier history is busy right now. Try again in a moment.',
            ]);
        }

        try {
            $merchant = $merchant->fresh() ?? $merchant;

            if (! $merchant->courier_history_enabled) {
                throw ValidationException::withMessages([
                    'phones' => 'Courier history is not enabled for this account.',
                ]);
            }

            $needsNewSlot = collect($parsed['entries'])
                ->where('invalid', false)
                ->contains(fn (array $entry): bool => ! $this->quota->alreadyCounted($merchant, $entry['phone']));

            if ($needsNewSlot && $this->quota->snapshot($merchant)['remaining'] < 1) {
                throw ValidationException::withMessages([
                    'phones' => "Today's courier history limit is used up.",
                ]);
            }

            $cached = BulkFraudDailyResult::query()
                ->where('checked_on', $this->today()->toDateString())
                ->whereIn('phone', collect($parsed['entries'])->where('invalid', false)->pluck('phone')->all())
                ->get()
                ->keyBy('phone');

            return DB::transaction(function () use ($merchant, $name, $parsed, $cached): BulkFraudBatch {
                $batch = BulkFraudBatch::query()->create([
                    'user_id' => $merchant->id,
                    'name' => $name !== '' ? $name : 'Bulk-'.$this->today()->format('d-m-y-H-i'),
                    'status' => 'running',
                    'submitted_count' => $parsed['submitted'],
                    'unique_count' => count($parsed['entries']),
                    'duplicates_removed' => $parsed['duplicates_removed'],
                ]);

                foreach ($parsed['entries'] as $position => $entry) {
                    $item = new BulkFraudBatchItem([
                        'batch_id' => $batch->id,
                        'position' => $position,
                        'phone' => $entry['phone'],
                        'attempts' => 0,
                    ]);

                    if ($entry['invalid']) {
                        $this->applyRow($item, MerchantBulkFraudRow::invalid($entry['phone']), null, null);
                        $item->status = 'invalid';
                    } elseif (($daily = $cached->get($entry['phone'])) && ! $this->dailyResultNeedsRecheck($daily)) {
                        $counts = $daily->counts();
                        $successful = in_array($counts['status'] ?? '', ['found', 'not_found'], true);

                        if ($successful && $this->quota->allows($merchant, $entry['phone'])) {
                            $this->applyRow($item, $counts, 'cache', $daily->id);
                            $this->quota->record($merchant, $entry['phone']);
                        } else {
                            $item->status = 'pending';
                            $item->couriers = [];
                        }
                    } else {
                        $item->status = 'pending';
                        $item->couriers = [];
                    }

                    $item->save();
                }

                $this->finishIfIdle($batch);

                return $batch->load('items');
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Remember that someone has this batch open, so the server does not
     * check the same numbers the page is already checking.
     */
    public function touchPage(BulkFraudBatch $batch): void
    {
        try {
            Cache::put($this->pageKey($batch), now()->getTimestamp(), self::PAGE_OPEN_SECONDS + 60);
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));
        }
    }

    /**
     * Check pending numbers for batches whose page is not open.
     *
     * @return array{checked: int}
     */
    public function runUnattended(int $seconds): array
    {
        $deadline = now()->getTimestamp() + max(1, min(300, $seconds));
        $checked = 0;
        $request = Request::create('/portal/fraud-check/unattended', 'POST');

        while (now()->getTimestamp() < $deadline) {
            $batch = $this->nextUnattendedBatch();

            if ($batch === null) {
                break;
            }

            $result = $this->advance($request, $batch);

            if (! empty($result['item'])) {
                $checked++;
            }

            if (! empty($result['limit_reached'])) {
                break;
            }

            if (! empty($result['retry']) && empty($result['item'])) {
                Sleep::for(1)->seconds();
            }
        }

        return ['checked' => $checked];
    }

    public function hasUnattendedWork(): bool
    {
        return $this->nextUnattendedBatch() !== null;
    }

    /**
     * Check the next pending number, or reuse today's saved result.
     *
     * @return array{done: bool, retry: bool, batch: array<string, mixed>, item: array<string, mixed>|null}
     */
    public function advance(Request $request, BulkFraudBatch $batch): array
    {
        if ($batch->status === 'stopped') {
            return [
                'done' => false,
                'retry' => false,
                'batch' => $this->summary($batch),
                'item' => null,
            ];
        }

        $this->releaseStaleChecks($batch);

        $merchant = $this->merchant($batch);
        $slot = $this->workerSlot($merchant);

        if (! $slot) {
            return [
                'done' => false,
                'retry' => true,
                'batch' => $this->summary($batch),
                'item' => null,
            ];
        }

        try {
            return $this->checkNext($request, $batch, $merchant);
        } finally {
            $slot->release();
        }
    }

    /**
     * Number of a merchant's numbers checked at the same time.
     */
    public function workers(): int
    {
        return max(1, min(5, (int) config('fraud_check.bulk_workers', 3)));
    }

    /**
     * Times a missing courier of one row is asked again in the waiting lane.
     */
    public function incompleteRetries(): int
    {
        return $this->waitingLane->maxTries();
    }

    public function incompleteRetryGapSeconds(): int
    {
        return max(3, min(30, (int) config('fraud_check.bulk_incomplete_retry_gap_seconds', 5)));
    }

    /**
     * @return array{done: bool, retry: bool, batch: array<string, mixed>, item: array<string, mixed>|null}
     */
    private function checkNext(Request $request, BulkFraudBatch $batch, User $merchant): array
    {
        $item = $this->claimNext($batch);

        if (! $item) {
            $this->finishIfIdle($batch);
            $fresh = $batch->fresh();

            return [
                'done' => $fresh->status === 'completed',
                'retry' => $fresh->items()->whereIn('status', ['pending', 'checking'])->exists(),
                'batch' => $this->summary($fresh),
                'item' => null,
            ];
        }

        $guard = $this->guardedResolve(
            $merchant,
            $item->phone,
            fn () => $this->context->asBulk(fn () => $this->resolvePhone($request, $item->phone, $item->source === self::RECHECK_SOURCE)),
        );

        if ($guard['busy'] || $guard['failed']) {
            $this->putBack($item);

            return [
                'done' => false,
                'retry' => true,
                'limit_reached' => false,
                'batch' => $this->summary($batch->fresh()),
                'item' => $item->fresh()->toPortalRow(),
            ];
        }

        if ($guard['limit']) {
            $this->putBack($item);

            return [
                'done' => false,
                'retry' => false,
                'limit_reached' => true,
                'batch' => $this->summary($batch->fresh()),
                'item' => null,
            ];
        }

        $resolved = $guard['resolved'];
        $this->applyRow($item, $resolved['row'], $resolved['source'], $resolved['daily_id']);
        $this->waitingLane->track($item, $resolved['report'] ?? null);
        $item->attempts = $this->boundedAttempts((int) $resolved['attempts']);
        $item->save();
        $this->recordIfSuccessful($merchant, $item);
        $this->finishIfIdle($batch);

        return [
            'done' => $batch->fresh()->status === 'completed',
            'retry' => false,
            'batch' => $this->summary($batch->fresh()),
            'item' => $item->fresh()->toPortalRow(),
        ];
    }

    /**
     * Check one failed number again. A result already saved today is reused.
     *
     * @return array{done: bool, retry: bool, batch: array<string, mixed>, item: array<string, mixed>|null}
     */
    public function retry(Request $request, BulkFraudBatch $batch, BulkFraudBatchItem $item): array
    {
        if ($item->status !== 'error') {
            return [
                'done' => $batch->status === 'completed',
                'retry' => false,
                'batch' => $this->summary($batch),
                'item' => $item->toPortalRow(),
            ];
        }

        if ($batch->status === 'stopped') {
            $batch->status = 'running';
            $batch->save();
        }

        $merchant = $this->merchant($batch);
        $item->status = 'checking';
        $item->save();

        $guard = $this->guardedResolve($merchant, $item->phone, fn () => $this->resolvePhone($request, $item->phone));

        if ($guard['limit']) {
            $item->status = 'error';
            $item->save();

            return [
                'done' => false,
                'retry' => false,
                'limit_reached' => true,
                'batch' => $this->summary($batch->fresh()),
                'item' => $item->fresh()->toPortalRow(),
            ];
        }

        if ($guard['busy'] || $guard['failed']) {
            $item->status = 'error';
            $item->save();

            return [
                'done' => false,
                'retry' => true,
                'limit_reached' => false,
                'batch' => $this->summary($batch->fresh()),
                'item' => $item->fresh()->toPortalRow(),
            ];
        }

        $resolved = $guard['resolved'];
        $this->applyRow($item, $resolved['row'], $resolved['source'], $resolved['daily_id']);
        $this->waitingLane->track($item, $resolved['report'] ?? null);
        $item->attempts = $this->boundedAttempts((int) $item->attempts + (int) $resolved['attempts']);
        $item->save();
        $this->recordIfSuccessful($merchant, $item);
        $this->finishIfIdle($batch);

        return [
            'done' => $batch->fresh()->status === 'completed',
            'retry' => false,
            'batch' => $this->summary($batch->fresh()),
            'item' => $item->fresh()->toPortalRow(),
        ];
    }

    /**
     * Refetch one finished number from the couriers, skipping today's saved result.
     *
     * @return array{done: bool, refreshed: bool, batch: array<string, mixed>, item: array<string, mixed>|null}
     */
    public function refresh(Request $request, BulkFraudBatch $batch, BulkFraudBatchItem $item): array
    {
        if (! in_array($item->status, ['found', 'not_found', 'error'], true)) {
            return [
                'done' => $batch->status === 'completed',
                'refreshed' => false,
                'batch' => $this->summary($batch),
                'item' => $item->toPortalRow(),
            ];
        }

        $previous = [
            'status' => $item->status,
            'total_orders' => (int) $item->total_orders,
            'received' => (int) $item->received,
            'cancelled' => (int) $item->cancelled,
            'partial' => (int) $item->partial,
            'success_rate' => $item->success_rate,
            'risk' => $item->risk,
            'couriers' => $item->couriers ?? [],
        ];
        $previousSource = $item->source;
        $previousDailyId = $item->daily_result_id;
        $previousWaiting = [
            'waiting_couriers' => $item->waiting_couriers,
            'fill_report' => $item->fill_report,
            'next_fill_at' => $item->next_fill_at,
        ];

        $merchant = $this->merchant($batch);
        $guard = $this->guardedResolve($merchant, $item->phone, function () use ($request, $item) {
            $item->status = 'checking';
            $item->save();

            return $this->resolvePhone($request, $item->phone, true);
        });

        if ($guard['limit']) {
            return [
                'done' => $batch->status === 'completed',
                'refreshed' => false,
                'limit_reached' => true,
                'batch' => $this->summary($batch),
                'item' => $item->toPortalRow(),
            ];
        }

        if ($guard['busy'] || $guard['failed']) {
            $this->applyRow($item, $previous, $previousSource, $previousDailyId);
            $item->forceFill($previousWaiting);
            $item->save();

            return [
                'done' => $batch->fresh()->status === 'completed',
                'refreshed' => false,
                'batch' => $this->summary($batch->fresh()),
                'item' => $item->fresh()->toPortalRow(),
            ];
        }

        $resolved = $guard['resolved'];

        if (($resolved['row']['status'] ?? '') === 'error' && $previous['status'] !== 'error') {
            $this->applyRow($item, $previous, $previousSource, $previousDailyId);
            $item->forceFill($previousWaiting);
            $item->save();

            return [
                'done' => $batch->fresh()->status === 'completed',
                'refreshed' => false,
                'batch' => $this->summary($batch->fresh()),
                'item' => $item->fresh()->toPortalRow(),
            ];
        }

        $this->applyRow($item, $resolved['row'], $resolved['source'], $resolved['daily_id']);
        $this->waitingLane->track($item, $resolved['report'] ?? null);
        $item->attempts = $this->boundedAttempts((int) $item->attempts + (int) $resolved['attempts']);
        $item->save();
        $this->recordIfSuccessful($merchant, $item);
        $this->finishIfIdle($batch);

        return [
            'done' => $batch->fresh()->status === 'completed',
            'refreshed' => true,
            'batch' => $this->summary($batch->fresh()),
            'item' => $item->fresh()->toPortalRow(),
        ];
    }

    public function stop(BulkFraudBatch $batch): BulkFraudBatch
    {
        $waiting = $batch->items()->whereNotNull('next_fill_at')->exists();

        if ($batch->status === 'running' || ($waiting && $batch->status !== 'stopped')) {
            $batch->status = 'stopped';
            $batch->save();
        }

        return $batch->load('items');
    }

    public function resume(BulkFraudBatch $batch): BulkFraudBatch
    {
        if ($batch->status === 'stopped') {
            $batch->status = 'running';
            $batch->save();
        }

        return $batch->load('items');
    }

    /**
     * Check every number of this batch with the couriers again. Today's saved
     * result is skipped, not deleted: other merchants' batches point at it,
     * and the new answer replaces it. Invalid numbers stay invalid.
     */
    public function replayAsNew(BulkFraudBatch $batch): BulkFraudBatch
    {
        if ($batch->status === 'running' && $batch->items()->whereIn('status', ['pending', 'checking'])->exists()) {
            throw ValidationException::withMessages([
                'batch' => 'This batch is still checking. Pause it or let it finish, then check it again.',
            ]);
        }

        DB::transaction(function () use ($batch): void {
            $batch->items()->where('status', '!=', 'invalid')->update([
                'status' => 'pending',
                'source' => self::RECHECK_SOURCE,
                'daily_result_id' => null,
                'attempts' => 0,
                'total_orders' => 0,
                'received' => 0,
                'cancelled' => 0,
                'partial' => 0,
                'success_rate' => null,
                'risk' => null,
                'couriers' => '[]',
                'waiting_couriers' => null,
                'fill_report' => null,
                'next_fill_at' => null,
            ]);

            $batch->status = 'running';
            $batch->save();
        });

        return $batch->fresh()->load('items');
    }

    /**
     * Check one phone for the merchant portal. The same number on the same day
     * reuses the saved courier result and does not take another daily slot.
     *
     * @return array{result: array<string, mixed>, source: string|null}
     */
    public function search(Request $request, User $merchant, string $phone): array
    {
        if (! $merchant->courier_history_enabled) {
            throw ValidationException::withMessages([
                'phone' => 'Courier history is not enabled for this account.',
            ]);
        }

        try {
            $phone = $this->fraudCheckService->normalizePhone($phone);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->getMessage(),
            ]);
        }

        $guard = $this->guardedResolve($merchant, $phone, fn () => $this->resolvePhone($request, $phone));

        if ($guard['limit']) {
            throw ValidationException::withMessages([
                'phone' => "Today's courier history limit is used up.",
            ]);
        }

        if ($guard['busy']) {
            throw ValidationException::withMessages([
                'phone' => 'Courier history is busy right now. Try again in a moment.',
            ]);
        }

        if ($guard['failed'] || ! is_array($guard['resolved'])) {
            throw ValidationException::withMessages([
                'phone' => 'Could not check this number. Try again.',
            ]);
        }

        $resolved = $guard['resolved'];
        $row = $resolved['row'];
        $row['phone'] = $phone;

        return [
            'result' => $row,
            'source' => $resolved['source'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(BulkFraudBatch $batch): array
    {
        $counts = BulkFraudBatchItem::query()
            ->where('batch_id', $batch->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($counts['pending'] ?? 0) + (int) ($counts['checking'] ?? 0);
        $waiting = BulkFraudBatchItem::query()
            ->where('batch_id', $batch->id)
            ->whereNotNull('next_fill_at')
            ->count();

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'submitted_count' => (int) $batch->submitted_count,
            'unique_count' => (int) $batch->unique_count,
            'duplicates_removed' => (int) $batch->duplicates_removed,
            'pending' => $pending,
            'waiting' => $waiting,
            'created_at' => optional($batch->created_at)?->toIso8601String(),
        ];
    }

    /**
     * Rows of $batch changed since $since, with the batch summary and the
     * couriers its rows wait for. Starts a short waiting-lane pass when the
     * scheduler has not run for a while.
     *
     * @return array{batch: array<string, mixed>, items: list<array<string, mixed>>, couriers: list<array<string, mixed>>, server_time: string}
     */
    public function changes(BulkFraudBatch $batch, ?Carbon $since): array
    {
        $this->touchPage($batch);
        $this->waitingLane->fillReadyIfIdle($batch);
        $serverTime = now();

        $items = BulkFraudBatchItem::query()
            ->where('batch_id', $batch->id)
            ->when($since !== null, fn ($query) => $query->where('updated_at', '>=', $since->copy()->subSeconds(2)))
            ->orderBy('position')
            ->limit(1000)
            ->get();

        $this->waitingLane->nudgeIfIdle();

        return [
            'batch' => $this->summary($batch->fresh() ?? $batch),
            'items' => $items->map->toPortalRow()->values()->all(),
            'couriers' => $this->waitingLane->courierStatus($batch),
            'server_time' => $serverTime->toIso8601String(),
        ];
    }

    /**
     * Ask the missing courier for up to three waiting numbers. Does not run a full check.
     *
     * @return array{batch: array<string, mixed>, items: list<array<string, mixed>>, asked: int}
     */
    public function tryWaiting(BulkFraudBatch $batch): array
    {
        $asked = $this->waitingLane->tryNow($batch, 3);
        $batch = $batch->fresh() ?? $batch;

        return [
            'batch' => $this->summary($batch),
            'items' => $batch->items()->orderBy('position')->get()->map->toPortalRow()->values()->all(),
            'asked' => $asked,
        ];
    }

    /**
     * @return array{submitted: int, duplicates_removed: int, entries: list<array{phone: string, invalid: bool}>}
     */
    public function parse(string $raw): array
    {
        $submitted = 0;
        $seen = [];
        $entries = [];

        foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $submitted++;
            $normalizedLine = strtr($line, [
                '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
                '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            $digits = preg_replace('/\D/', '', $normalizedLine) ?? '';

            if (str_starts_with($digits, '00880')) {
                $digits = substr($digits, 4);
            } elseif (str_starts_with($digits, '880')) {
                $digits = substr($digits, 2);
            } elseif (strlen($digits) === 12 && preg_match('/^881[3-9]\d{8}$/', $digits)) {
                $digits = '0'.substr($digits, 2);
            } elseif (strlen($digits) === 10 && preg_match('/^1[3-9]\d{8}$/', $digits)) {
                $digits = '0'.$digits;
            }

            try {
                $phone = $this->fraudCheckService->normalizePhone($digits !== '' ? $digits : $line);
                $key = $phone;
                $invalid = false;
            } catch (InvalidArgumentException) {
                $phone = $digits !== '' ? mb_substr($digits, 0, 32) : mb_substr($line, 0, 32);
                $key = 'invalid:'.$phone;
                $invalid = true;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $entries[] = [
                'phone' => $phone,
                'invalid' => $invalid,
            ];
        }

        return [
            'submitted' => $submitted,
            'duplicates_removed' => $submitted - count($entries),
            'entries' => $entries,
        ];
    }

    /**
     * `report` is the courier report a live row was built from, so couriers
     * that did not answer can wait for another ask; null for a saved row.
     *
     * @return array{row: array<string, mixed>, source: string, daily_id: int|null, attempts: int, report: array<string, mixed>|null}
     */
    private function resolvePhone(Request $request, string $phone, bool $refresh = false): array
    {
        if (! $refresh) {
            $existing = $this->reusableDailyResult($phone);

            if ($existing) {
                return [
                    'row' => $existing->counts(),
                    'source' => 'cache',
                    'daily_id' => $existing->id,
                    'attempts' => 0,
                    'report' => null,
                ];
            }
        }

        $lock = Cache::lock('bulk-fraud-daily:'.$phone.':'.$this->today()->toDateString(), $this->staleSeconds() + 30);
        $lock->block(20);

        try {
            if (! $refresh) {
                $existing = $this->reusableDailyResult($phone);

                if ($existing) {
                    return [
                        'row' => $existing->counts(),
                        'source' => 'cache',
                        'daily_id' => $existing->id,
                        'attempts' => 0,
                        'report' => null,
                    ];
                }
            }

            $checked = $this->checkWithRetries($request, $phone, $refresh);

            if ($checked['report'] === null) {
                return [
                    'row' => MerchantBulkFraudRow::failed($phone),
                    'source' => 'live',
                    'daily_id' => null,
                    'attempts' => $checked['attempts'],
                    'report' => null,
                ];
            }

            $row = MerchantBulkFraudRow::fromReport($phone, $checked['report']);
            $attributes = [
                'status' => $row['status'],
                'total_orders' => $row['total_orders'],
                'received' => $row['received'],
                'cancelled' => $row['cancelled'],
                'partial' => $row['partial'],
                'success_rate' => $row['success_rate'],
                'risk' => $row['risk'],
                'couriers' => $row['couriers'],
                'attempts' => $this->boundedAttempts((int) $checked['attempts']),
            ];

            try {
                $alreadySaved = $this->dailyResult($phone) !== null;
                $daily = ($refresh || $alreadySaved)
                    ? BulkFraudDailyResult::query()->updateOrCreate(
                        [
                            'phone' => $phone,
                            'checked_on' => $this->today()->toDateString(),
                        ],
                        $attributes,
                    )
                    : BulkFraudDailyResult::query()->create([
                        'phone' => $phone,
                        'checked_on' => $this->today()->toDateString(),
                        ...$attributes,
                    ]);
            } catch (QueryException) {
                $existing = $this->dailyResult($phone);

                if (! $existing) {
                    throw new \RuntimeException('Could not save the daily fraud result.');
                }

                return [
                    'row' => $existing->counts(),
                    'source' => 'cache',
                    'daily_id' => $existing->id,
                    'attempts' => 0,
                    'report' => null,
                ];
            }

            return [
                'row' => $row,
                'source' => 'live',
                'daily_id' => $daily->id,
                'attempts' => $checked['attempts'],
                'report' => $checked['report'],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{report: array<string, mixed>|null, attempts: int}
     */
    private function checkWithRetries(Request $request, string $phone, bool $refresh = false): array
    {
        $attempts = max(1, (int) config('fraud_check.bulk_retry_attempts', 3));
        $pauseMs = max(0, (int) config('fraud_check.bulk_retry_pause_ms', 300));
        $budgetSeconds = max(1, (int) config('fraud_check.bulk_number_budget_seconds', 60));
        $startedAt = microtime(true);
        $payload = ['phone' => $phone];

        if ($refresh) {
            $payload['refresh'] = true;
        }

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $report = $this->coordinator->checkSingle($request, $payload);

                if (is_array($report) && MerchantBulkFraudRow::isUsable($report)) {
                    return [
                        'report' => $this->askMissingCouriers($request, $payload, $report, $startedAt, $budgetSeconds),
                        'attempts' => $attempt,
                    ];
                }
            } catch (\Throwable $exception) {
                LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));
            }

            if ($attempt >= $attempts || microtime(true) - $startedAt >= $budgetSeconds) {
                return ['report' => null, 'attempts' => $attempt];
            }

            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
        }

        return ['report' => null, 'attempts' => $attempts];
    }

    /**
     * When SteadFast, Pathao, or Paperfly did not answer, ask only that
     * courier again. A courier that had no room, asked us to slow down, is
     * paused, rejected the credentials, or keeps failing is not asked here;
     * the waiting lane asks it later.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function askMissingCouriers(Request $request, array $payload, array $report, float $startedAt, int $budgetSeconds): array
    {
        $retries = max(0, (int) config('fraud_check.bulk_missing_courier_retries', 1));
        $pauseMs = max(0, (int) config('fraud_check.bulk_retry_pause_ms', 300));

        for ($retry = 0; $retry < $retries; $retry++) {
            if (microtime(true) - $startedAt >= $budgetSeconds) {
                break;
            }

            $couriers = [];
            foreach (MerchantBulkFraudRow::unansweredCoreCouriers($report) as $courier => $courierReport) {
                if (
                    ($courierReport['retryable'] ?? true) !== false
                    && empty($courierReport['rate_limited'])
                    && empty($courierReport['deferred'])
                    && empty($courierReport['retry_later'])
                    && empty($courierReport['credential_error'])
                    && ! FraudCheckService::courierRetryPaused($courier)
                ) {
                    $couriers[] = $courier;
                }
            }

            if ($couriers === []) {
                break;
            }

            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }

            try {
                $report = $this->coordinator->retryCouriers($request, $payload, $report, $couriers);
            } catch (\Throwable $exception) {
                LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

                break;
            }
        }

        return $report;
    }

    private function dailyResult(string $phone): ?BulkFraudDailyResult
    {
        return BulkFraudDailyResult::query()
            ->where('phone', $phone)
            ->where('checked_on', $this->today()->toDateString())
            ->first();
    }

    /**
     * A saved row is reused for the rest of the day only when every courier
     * answered. A courier that did not answer is asked again after the
     * recheck window, without using another daily quota slot.
     */
    private function reusableDailyResult(string $phone): ?BulkFraudDailyResult
    {
        $existing = $this->dailyResult($phone);

        if ($existing === null || $this->dailyResultNeedsRecheck($existing)) {
            return null;
        }

        return $existing;
    }

    private function dailyResultNeedsRecheck(BulkFraudDailyResult $existing): bool
    {
        $hasOrders = ((int) $existing->total_orders + (int) $existing->received + (int) $existing->cancelled + (int) $existing->partial) > 0;
        if ($existing->status === 'found' && ! $hasOrders && (int) ($existing->success_rate ?? 0) === 0) {
            return true;
        }

        foreach ($existing->couriers ?? [] as $courier) {
            if (! is_array($courier) || ! array_key_exists('available', $courier) || $courier['available'] !== false) {
                continue;
            }

            $key = MerchantBulkFraudRow::courierKeyFromTitle((string) ($courier['title'] ?? ''));
            // Missing Pathao/SteadFast/Paperfly must not be reused — that is the
            // "Found overall, Pathao Data unavailable until Refresh" bug.
            if (in_array($key, ['steadfast', 'pathao', 'paperfly'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyRow(BulkFraudBatchItem $item, array $row, ?string $source, ?int $dailyId): void
    {
        $item->status = (string) $row['status'];
        $item->source = $source;
        $item->daily_result_id = $dailyId;
        $item->total_orders = (int) ($row['total_orders'] ?? 0);
        $item->received = (int) ($row['received'] ?? 0);
        $item->cancelled = (int) ($row['cancelled'] ?? 0);
        $item->partial = (int) ($row['partial'] ?? 0);
        $item->success_rate = $row['success_rate'] ?? null;
        $item->risk = $row['risk'] ?? null;
        $item->couriers = $row['couriers'] ?? [];
    }

    private function nextUnattendedBatch(): ?BulkFraudBatch
    {
        $batches = BulkFraudBatch::query()
            ->where('status', 'running')
            ->whereHas('items', fn ($query) => $query->where('status', 'pending'))
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($batches as $batch) {
            if (! $this->pageIsOpen($batch)) {
                return $batch;
            }
        }

        return null;
    }

    private function pageIsOpen(BulkFraudBatch $batch): bool
    {
        try {
            $seen = (int) Cache::get($this->pageKey($batch), 0);
        } catch (\Throwable) {
            return false;
        }

        return $seen > 0 && (now()->getTimestamp() - $seen) < self::PAGE_OPEN_SECONDS;
    }

    private function pageKey(BulkFraudBatch $batch): string
    {
        return 'bulk-fraud-page:'.$batch->id;
    }

    private function releaseStaleChecks(BulkFraudBatch $batch): void
    {
        BulkFraudBatchItem::query()
            ->where('batch_id', $batch->id)
            ->where('status', 'checking')
            ->where('updated_at', '<', now()->subSeconds($this->staleSeconds()))
            ->update(['status' => 'pending']);
    }

    private function staleSeconds(): int
    {
        return max(60, (int) config('fraud_check.bulk_stale_check_seconds', 150));
    }

    /**
     * Take the next pending number. Several requests can run at once, so
     * the number is taken only if it is still pending.
     */
    private function claimNext(BulkFraudBatch $batch): ?BulkFraudBatchItem
    {
        for ($try = 0; $try < 5; $try++) {
            $next = BulkFraudBatchItem::query()
                ->where('batch_id', $batch->id)
                ->where('status', 'pending')
                ->orderBy('position')
                ->first();

            if (! $next) {
                return null;
            }

            $claimed = BulkFraudBatchItem::query()
                ->whereKey($next->id)
                ->where('status', 'pending')
                ->update(['status' => 'checking']);

            if ($claimed === 1) {
                return $next->fresh();
            }
        }

        return null;
    }

    /**
     * Return a number to the queue unless another request already finished it.
     */
    private function putBack(BulkFraudBatchItem $item): void
    {
        BulkFraudBatchItem::query()
            ->whereKey($item->id)
            ->where('status', 'checking')
            ->update(['status' => 'pending']);
    }

    /**
     * At most workers() numbers of one merchant are checked at the same
     * time, however many tabs run a batch.
     */
    private function workerSlot(User $merchant): ?Lock
    {
        $seconds = $this->staleSeconds() + 30;

        for ($slot = 0; $slot < $this->workers(); $slot++) {
            try {
                $lock = Cache::lock('bulk-fraud-worker:'.$merchant->id.':'.$slot, $seconds);

                if ($lock->get()) {
                    return $lock;
                }
            } catch (\Throwable $exception) {
                LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

                return null;
            }
        }

        return null;
    }

    private function finishIfIdle(BulkFraudBatch $batch): void
    {
        $pending = $batch->items()->whereIn('status', ['pending', 'checking'])->exists();

        if (! $pending && in_array($batch->status, ['running', 'stopped'], true)) {
            $batch->status = 'completed';
            $batch->save();
        }
    }

    private function merchant(BulkFraudBatch $batch): User
    {
        return User::query()->findOrFail($batch->user_id);
    }

    /**
     * A completed courier answer counts once per number per day.
     * The same number later in the day, including a refresh, does not count again.
     */
    private function recordIfSuccessful(User $merchant, BulkFraudBatchItem $item): void
    {
        if (! in_array($item->status, ['found', 'not_found'], true)) {
            return;
        }

        $this->quota->record($merchant, $item->phone);
    }

    /**
     * @param  callable(): array{row: array<string, mixed>, source: string, daily_id: int|null, attempts: int}  $resolve
     * @return array{limit: bool, busy: bool, failed: bool, resolved: array<string, mixed>|null}
     */
    private function guardedResolve(User $merchant, string $phone, callable $resolve): array
    {
        $lock = Cache::lock('courier-history-merchant:'.$merchant->id, 30);

        try {
            $lock->block(8);
        } catch (LockTimeoutException) {
            return ['limit' => false, 'busy' => true, 'failed' => false, 'resolved' => null];
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

            return ['limit' => false, 'busy' => true, 'failed' => false, 'resolved' => null];
        }

        // Only the limit check and the slot are inside the merchant lock, so
        // several numbers of one merchant can call the couriers at once.
        try {
            $fresh = $merchant->fresh() ?? $merchant;

            if (! $this->quota->allows($fresh, $phone)) {
                return ['limit' => true, 'busy' => false, 'failed' => false, 'resolved' => null];
            }

            $reserved = $this->quota->reserve($fresh, $phone);
        } finally {
            $lock->release();
        }

        try {
            $resolved = $resolve();
        } catch (\Throwable $exception) {
            $this->quota->release($reserved);
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

            return ['limit' => false, 'busy' => false, 'failed' => true, 'resolved' => null];
        }

        if (in_array($resolved['row']['status'] ?? '', ['found', 'not_found'], true)) {
            $this->quota->record($fresh, $phone);
        } else {
            $this->quota->release($reserved);
        }

        return [
            'limit' => false,
            'busy' => false,
            'failed' => false,
            'resolved' => $resolved,
        ];
    }

    private function boundedAttempts(int $attempts): int
    {
        return max(0, min(255, $attempts));
    }

    private function today(): Carbon
    {
        return Carbon::now('Asia/Dhaka');
    }
}
