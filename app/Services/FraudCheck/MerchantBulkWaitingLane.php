<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use App\Models\BulkFraudBatch;
use App\Models\BulkFraudBatchItem;
use App\Models\BulkFraudDailyResult;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

/**
 * A bulk number whose SteadFast, Pathao, or Paperfly answer is missing waits
 * here instead of being saved as Incomplete. Each courier is asked again on
 * its own, only when it has room. A try counts only when the courier was
 * really asked and did not answer; after the last try, or after the waiting
 * time runs out, the number is saved as Incomplete.
 */
class MerchantBulkWaitingLane
{
    public const CORE = ['steadfast', 'pathao', 'paperfly'];

    private const HEARTBEAT_KEY = 'bulk_waiting_lane:heartbeat';

    private const NUDGE_KEY = 'bulk_waiting_lane:nudge';

    /** Seconds without a scheduled run after which a page poll starts a short one. */
    private const IDLE_SECONDS = 20;

    private const MAX_SLEEP_SECONDS = 10;

    /** A courier that is busy without saying for how long rests this lane, up to this many seconds. */
    private const MAX_REST_SECONDS = 300;

    /** Minutes of lane answers a courier's real speed is measured over. */
    private const RATE_MINUTES = 5;

    public function __construct(
        private FraudCheckCoordinator $coordinator,
        private CourierCallContext $context,
    ) {}

    /**
     * Set the waiting fields of $item from the report it was just checked
     * with. $previous keeps the tries and start time of a row that was
     * already waiting.
     *
     * @param  array<string, mixed>|null  $report
     * @param  array<string, array<string, mixed>>  $previous
     */
    public function track(BulkFraudBatchItem $item, ?array $report, array $previous = []): void
    {
        $waiting = is_array($report) && in_array($item->status, ['found', 'not_found'], true)
            ? $this->plan($report, $previous)
            : [];

        $this->setWaiting($item, $report, $waiting);
    }

    /**
     * Couriers to ask again, with their tries, start time, next ask time,
     * and reason.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, array<string, mixed>>  $previous
     * @return array<string, array{tries: int, since: int, next_at: int, reason: string}>
     */
    public function plan(array $report, array $previous = []): array
    {
        if ($this->maxTries() <= 0) {
            return [];
        }

        $now = $this->clock();
        $waiting = [];

        foreach (MerchantBulkFraudRow::couriersToAskLater($report) as $courier => $courierReport) {
            $tries = (int) ($previous[$courier]['tries'] ?? 0);

            $waiting[$courier] = [
                'tries' => $tries,
                'since' => (int) ($previous[$courier]['since'] ?? $now),
                'next_at' => $now + $this->delay($courierReport, $tries),
                'reason' => MerchantBulkFraudRow::unavailableReason($courierReport),
            ];
        }

        return $waiting;
    }

    /**
     * Ask the due couriers of waiting rows for up to $seconds. With $wait,
     * sleeps until the next courier has room; otherwise stops when nothing
     * can be asked right now.
     *
     * @return array{asked: int, finished: int}
     */
    public function run(int $seconds, bool $wait = true): array
    {
        $deadline = $this->clock() + max(1, $seconds);
        $stats = ['asked' => 0, 'finished' => 0];

        while ($this->clock() < $deadline) {
            $this->beat();
            $progress = false;

            foreach (self::CORE as $courier) {
                if ($this->clock() >= $deadline) {
                    break;
                }

                if ($this->slotSeconds($courier) > 0) {
                    continue;
                }

                // A courier with room can take a few waiting numbers on this pass.
                for ($turn = 0; $turn < 3 && $this->clock() < $deadline; $turn++) {
                    if ($turn > 0 && $this->slotSeconds($courier) > 0) {
                        break;
                    }

                    $item = $this->nextDue($courier);

                    if ($item === null) {
                        break;
                    }

                    $outcome = $this->fill($item);
                    $stats['asked'] += count($outcome['asked']);
                    $stats['finished'] += $outcome['finished'] ? 1 : 0;
                    $progress = $progress || $outcome['changed'];

                    if ($outcome['asked'] === []) {
                        break;
                    }
                }
            }

            if ($progress) {
                continue;
            }

            if (! $wait) {
                break;
            }

            $sleep = $this->secondsUntilWork();

            if ($sleep === null || $this->clock() + $sleep >= $deadline) {
                break;
            }

            Sleep::for(max(1, $sleep))->seconds();
        }

        return $stats;
    }

    /**
     * Ask up to a few due rows of this batch now. A page poll uses this so
     * waiting numbers are tried while the batch is open, without waiting for
     * the scheduler. $live waits briefly for a slot the way a manual refresh does.
     */
    public function fillReady(BulkFraudBatch $batch, int $limit = 3, bool $live = false): int
    {
        $limit = max(1, min(3, $limit));
        $askedRows = 0;

        foreach (self::CORE as $courier) {
            $guard = 0;

            while ($askedRows < $limit && $guard < $limit) {
                $guard++;
                $item = $this->nextDue($courier, $batch);

                if ($item === null) {
                    break;
                }

                $outcome = $this->fill($item, $live);

                if ($outcome['asked'] === []) {
                    break;
                }

                $askedRows++;
                $fresh = $item->fresh();

                // The courier asked us to wait. Another number now would only queue behind the same rest.
                if ($fresh !== null && $fresh->next_fill_at !== null && $fresh->next_fill_at->isFuture()) {
                    break;
                }
            }

            if ($askedRows >= $limit) {
                break;
            }
        }

        if ($askedRows > 0) {
            $this->beat();
        }

        return $askedRows;
    }

    /**
     * The page poll's share of the work: only when no background pass ran
     * recently. A running filler already uses every free courier slot.
     */
    public function fillReadyIfIdle(BulkFraudBatch $batch): int
    {
        try {
            if ($this->clock() - (int) Cache::get(self::HEARTBEAT_KEY, 0) < self::IDLE_SECONDS) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }

        return $this->fillReady($batch, 3);
    }

    /**
     * Ask up to three waiting numbers of this batch now, only for the courier
     * that has not answered. Used by the manual "Try 3 now" button.
     */
    public function tryNow(BulkFraudBatch $batch, int $limit = 3): int
    {
        $limit = max(1, min(3, $limit));
        $now = $this->clock();
        $items = $this->waitingForAny()
            ->where('batch_id', $batch->id)
            ->orderBy('next_fill_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($items as $item) {
            $waiting = $item->waitingCouriers();

            foreach ($waiting as $courier => $entry) {
                $entry['next_at'] = $now;
                $waiting[$courier] = $entry;
            }

            $item->waiting_couriers = $waiting;
            $item->next_fill_at = now();
            $item->save();
        }

        return $this->fillReady($batch, $limit, live: true);
    }

    /**
     * Ask every due courier of one row that has room now, then save the row.
     *
     * @return array{changed: bool, asked: list<string>, finished: bool}
     */
    public function fill(BulkFraudBatchItem $item, bool $live = false): array
    {
        $outcome = ['changed' => false, 'asked' => [], 'finished' => false];
        $lock = Cache::lock('bulk-fraud-daily:'.$item->phone.':'.$this->today()->toDateString(), 120);

        try {
            if (! $lock->get()) {
                return $outcome;
            }
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk waiting lane error', CourierTransport::safeMessage($exception));

            return $outcome;
        }

        try {
            $item->refresh();
            $waiting = $item->waitingCouriers();

            if ($waiting === [] || ! in_array($item->status, ['found', 'not_found'], true)) {
                $this->setWaiting($item, null, []);
                $item->save();

                return ['changed' => true, 'asked' => [], 'finished' => true];
            }

            // Another check already got every courier for this number today.
            $daily = $item->daily_result_id ? BulkFraudDailyResult::query()->find($item->daily_result_id) : null;
            if ($daily && MerchantBulkFraudRow::hasEveryCoreCourier(is_array($daily->couriers) ? $daily->couriers : [])) {
                $this->applyRow($item, $daily->counts());
                $this->setWaiting($item, null, []);
                $item->save();

                return ['changed' => true, 'asked' => [], 'finished' => true];
            }

            $report = is_array($item->fill_report) ? $item->fill_report : null;

            if ($report === null) {
                $this->setWaiting($item, null, []);
                $item->risk = $this->settledRisk($item);
                $item->save();

                return ['changed' => true, 'asked' => [], 'finished' => true];
            }

            $now = $this->clock();
            $due = [];

            foreach ($waiting as $courier => $entry) {
                if ($this->expired($entry, $now)) {
                    unset($waiting[$courier]);
                    $outcome['changed'] = true;

                    continue;
                }

                if ((int) ($entry['next_at'] ?? 0) <= $now && ($live || $this->slotSeconds($courier) === 0)) {
                    $due[] = $courier;
                }
            }

            if ($due !== []) {
                $ask = fn () => $this->coordinator->askCouriers(
                    Request::create('/portal/fraud-check/waiting', 'POST'),
                    ['phone' => $item->phone],
                    $report,
                    $due,
                );
                $asked = $live ? $ask() : $this->context->asBulk($ask);

                $report = $asked['report'];
                $outcome['asked'] = $due;
                $outcome['changed'] = true;

                foreach ($due as $courier) {
                    $this->noteAsked($courier);

                    if (in_array($courier, $asked['answered'], true)) {
                        unset($waiting[$courier]);
                        $this->noteAnswered($courier);

                        continue;
                    }

                    $answer = $asked['live'][$courier] ?? null;
                    $entry = $waiting[$courier];

                    if (! is_array($answer) || (empty($answer['deferred']) && empty($answer['rate_limited']))) {
                        $entry['tries'] = (int) $entry['tries'] + 1;
                    }

                    if (is_array($answer)) {
                        $report = $this->replaceCourierReport($report, $courier, $answer);
                        $entry['reason'] = MerchantBulkFraudRow::unavailableReason($answer);
                    }

                    if (
                        in_array($entry['reason'], ['sample', 'credentials', 'not_set_up'], true)
                        || $entry['tries'] >= $this->maxTries()
                    ) {
                        unset($waiting[$courier]);

                        continue;
                    }

                    $wait = $this->delay(is_array($answer) ? $answer : [], (int) $entry['tries']);

                    if (is_array($answer) && $this->busyWithoutWait($answer)) {
                        $wait = max($wait, $this->restCourier($courier));
                    }

                    $entry['next_at'] = $this->clock() + $wait;
                    $waiting[$courier] = $entry;
                }
            }

            if (! $outcome['changed']) {
                // Nothing could be asked now. Point the row at its next ask
                // time so the rows behind it get their turn.
                $nextAt = $this->nextAt($waiting);
                if ($nextAt !== null && $nextAt > $now && $item->next_fill_at?->getTimestamp() !== $nextAt) {
                    $item->next_fill_at = now()->setTimestamp($nextAt);
                    $item->save();
                }

                return $outcome;
            }

            $row = MerchantBulkFraudRow::fromReport($item->phone, $report);
            $this->applyRow($item, $row);
            $this->setWaiting($item, $report, $waiting);
            $item->save();
            $this->saveDaily($item, $row);

            $outcome['finished'] = $waiting === [];

            return $outcome;
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk waiting lane error', CourierTransport::safeMessage($exception));

            // Leave this row alone for a minute instead of failing on every pass.
            try {
                BulkFraudBatchItem::query()->whereKey($item->id)->whereNotNull('next_fill_at')->update(['next_fill_at' => now()->addMinute()]);
            } catch (\Throwable) {
                //
            }

            return $outcome;
        } finally {
            $lock->release();
        }
    }

    /**
     * Couriers with waiting rows in $batch: how many, the bulk pace, about
     * how long the whole queue of that courier takes, and when a paused
     * courier is asked again.
     *
     * The pace is what the courier allows; when the lane has been answering
     * slower than that for a few minutes, the time left follows the real
     * speed instead.
     *
     * @return list<array{courier: string, waiting: int, per_minute: float, resting_seconds: int, eta_minutes: int|null, paused_until: string|null}>
     */
    public function courierStatus(BulkFraudBatch $batch): array
    {
        $mine = $this->waitingCounts(
            BulkFraudBatchItem::query()->where('batch_id', $batch->id)->whereNotNull('next_fill_at')->pluck('waiting_couriers')->all(),
        );

        if ($mine === []) {
            return [];
        }

        $everyone = $this->waitingCounts(
            BulkFraudBatchItem::query()->whereNotNull('next_fill_at')->pluck('waiting_couriers')->all(),
        );

        // While the first pass runs it takes the courier slots, so the lane's own speed says little.
        $firstPassDone = ! $batch->items()->whereIn('status', ['pending', 'checking'])->exists();
        $status = [];

        foreach (self::CORE as $courier) {
            if (! isset($mine[$courier])) {
                continue;
            }

            $pace = $this->paceStatus($courier);
            $resting = max($pace['resting_seconds'], $this->laneRestSeconds($courier));
            $perMinute = $pace['per_minute'];
            $measured = $firstPassDone && $resting === 0 ? $this->measuredRate($courier) : null;

            if ($measured !== null && $measured < $perMinute) {
                $perMinute = max(0.2, $measured);
            }

            $queue = max($mine[$courier], $everyone[$courier] ?? 0);

            $status[] = [
                'courier' => $courier,
                'waiting' => $mine[$courier],
                'per_minute' => round($perMinute, 1),
                'resting_seconds' => $resting,
                'eta_minutes' => $perMinute > 0 ? (int) ceil($queue / $perMinute) + (int) ceil($resting / 60) : null,
                'paused_until' => $resting > 60 ? now()->addSeconds($resting)->toIso8601String() : null,
            ];
        }

        return $status;
    }

    /**
     * Start a short pass after the response when no scheduled run was seen
     * for a while, so waiting rows still move without the scheduler.
     */
    public function nudgeIfIdle(): void
    {
        try {
            if ($this->clock() - (int) Cache::get(self::HEARTBEAT_KEY, 0) < self::IDLE_SECONDS) {
                return;
            }

            if (! $this->waitingForAny()->where('next_fill_at', '<=', now())->exists()) {
                return;
            }

            if (! Cache::add(self::NUDGE_KEY, true, 60)) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        dispatch(function (): void {
            app(self::class)->run(35, wait: false);
        })->afterResponse();
    }

    /**
     * The parts of a coordinator report the row is rebuilt from. Customer
     * names are not kept.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public static function strip(array $report): array
    {
        $stripped = array_intersect_key($report, array_flip(['total_order', 'confirmed', 'cancel', 'success_rate', 'partial']));
        $stripped['frauds'] = self::stripFrauds($report['frauds'] ?? []);
        $stripped['courier_fraud_notes'] = self::stripFrauds($report['courier_fraud_notes'] ?? []);
        $stripped['courier'] = [];

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $courierReport = is_array($entry['report'] ?? null) ? $entry['report'] : [];

            if (array_key_exists('frauds', $courierReport)) {
                $courierReport['frauds'] = self::stripFrauds($courierReport['frauds']);
            }

            unset($courierReport['raw'], $courierReport['raw_report'], $courierReport['customer'], $courierReport['name'], $courierReport['customer_name']);

            $stripped['courier'][] = [
                'title' => (string) ($entry['title'] ?? ''),
                'report' => $courierReport,
            ];
        }

        return $stripped;
    }

    public function maxTries(): int
    {
        return max(0, min(5, (int) config('fraud_check.bulk_incomplete_retries', 5)));
    }

    /**
     * @param  array<string, array<string, mixed>>  $waiting
     * @param  array<string, mixed>|null  $report
     */
    private function setWaiting(BulkFraudBatchItem $item, ?array $report, array $waiting): void
    {
        if ($waiting === [] || $report === null) {
            $item->waiting_couriers = null;
            $item->fill_report = null;
            $item->next_fill_at = null;

            if ($item->risk === 'waiting') {
                $item->risk = $this->settledRisk($item);
            }

            return;
        }

        $item->risk = 'waiting';
        $item->waiting_couriers = $waiting;
        $item->fill_report = self::strip($report);
        $item->next_fill_at = now()->setTimestamp((int) $this->nextAt($waiting));
    }

    /**
     * The risk of a row that stopped waiting: Incomplete when a core
     * courier is still missing.
     */
    private function settledRisk(BulkFraudBatchItem $item): ?string
    {
        if (! MerchantBulkFraudRow::hasEveryCoreCourier(is_array($item->couriers) ? $item->couriers : [])) {
            return 'incomplete';
        }

        return MerchantBulkFraudRow::risk($item->success_rate === null ? null : (int) $item->success_rate);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyRow(BulkFraudBatchItem $item, array $row): void
    {
        $item->status = (string) ($row['status'] ?? $item->status);
        $item->total_orders = (int) ($row['total_orders'] ?? 0);
        $item->received = (int) ($row['received'] ?? 0);
        $item->cancelled = (int) ($row['cancelled'] ?? 0);
        $item->partial = (int) ($row['partial'] ?? 0);
        $item->success_rate = $row['success_rate'] ?? null;
        $item->risk = $row['risk'] ?? null;
        $item->couriers = $row['couriers'] ?? [];
    }

    /**
     * Keep the row's saved daily result in step. A complete result is not
     * replaced by an incomplete one.
     *
     * @param  array<string, mixed>  $row
     */
    private function saveDaily(BulkFraudBatchItem $item, array $row): void
    {
        if (! $item->daily_result_id) {
            return;
        }

        $daily = BulkFraudDailyResult::query()->find($item->daily_result_id);

        if (! $daily) {
            return;
        }

        $complete = MerchantBulkFraudRow::hasEveryCoreCourier($row['couriers'] ?? []);
        if (! $complete && MerchantBulkFraudRow::hasEveryCoreCourier(is_array($daily->couriers) ? $daily->couriers : [])) {
            return;
        }

        $daily->update([
            'status' => $row['status'],
            'total_orders' => $row['total_orders'],
            'received' => $row['received'],
            'cancelled' => $row['cancelled'],
            'partial' => $row['partial'],
            'success_rate' => $row['success_rate'],
            'risk' => $row['risk'],
            'couriers' => $row['couriers'],
        ]);
    }

    /**
     * The oldest row waiting for $courier whose ask time has come.
     */
    private function nextDue(string $courier, ?BulkFraudBatch $batch = null): ?BulkFraudBatchItem
    {
        $path = $this->nextAtPath($courier);

        return $this->waitingFor($courier)
            ->when($batch !== null, fn ($query) => $query->where('batch_id', $batch->id))
            ->where('next_fill_at', '<=', now())
            ->whereRaw('CAST(JSON_EXTRACT(waiting_couriers, ?) AS UNSIGNED) <= ?', [$path, $this->clock()])
            ->orderByRaw('CAST(JSON_EXTRACT(waiting_couriers, ?) AS UNSIGNED)', [$path])
            ->orderBy('id')
            ->first();
    }

    private function retryGap(): int
    {
        return max(3, min(30, (int) config('fraud_check.bulk_incomplete_retry_gap_seconds', 5)));
    }

    /**
     * Seconds until some waiting row is due and its courier has room, or
     * null when no row waits.
     */
    private function secondsUntilWork(): ?int
    {
        $soonest = null;

        foreach (self::CORE as $courier) {
            $nextAt = $this->waitingFor($courier)
                ->selectRaw('MIN(CAST(JSON_EXTRACT(waiting_couriers, ?) AS UNSIGNED)) AS next_at', [$this->nextAtPath($courier)])
                ->value('next_at');

            if ($nextAt === null) {
                continue;
            }

            $seconds = max(0, (int) $nextAt - $this->clock(), $this->slotSeconds($courier));
            $soonest = $soonest === null ? $seconds : min($soonest, $seconds);
        }

        return $soonest === null ? null : max(1, min(self::MAX_SLEEP_SECONDS, $soonest));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<BulkFraudBatchItem>
     */
    private function waitingFor(string $courier)
    {
        return $this->waitingForAny()
            ->whereRaw('JSON_EXTRACT(waiting_couriers, ?) IS NOT NULL', [$this->nextAtPath($courier)]);
    }

    /**
     * Waiting rows whose batch was not paused. A paused batch stays as it
     * is until the merchant resumes it.
     *
     * @return \Illuminate\Database\Eloquent\Builder<BulkFraudBatchItem>
     */
    private function waitingForAny()
    {
        return BulkFraudBatchItem::query()
            ->whereNotNull('next_fill_at')
            ->whereIn('status', ['found', 'not_found'])
            ->whereDoesntHave('batch', fn ($query) => $query->where('status', 'stopped'));
    }

    private function nextAtPath(string $courier): string
    {
        return '$."'.$courier.'".next_at';
    }

    /**
     * Seconds until $courier can take a bulk lookup, 0 when now.
     */
    private function slotSeconds(string $courier): int
    {
        try {
            $slot = $this->context->asBulk(fn (): int => match ($courier) {
                'pathao' => app(PathaoFraudChecker::class)->nextSlotSeconds(),
                'paperfly' => app(PaperflyFraudChecker::class)->nextSlotSeconds(),
                default => 0,
            });
        } catch (\Throwable) {
            $slot = 0;
        }

        return max($slot, $this->laneRestSeconds($courier));
    }

    /**
     * A busy answer without a wait time (for example SteadFast while its
     * store key cools down) can take seconds and still not answer. The lane
     * then leaves that courier alone for a while, longer each time, so the
     * other couriers are not held up behind it.
     *
     * @param  array<string, mixed>  $answer
     */
    private function busyWithoutWait(array $answer): bool
    {
        $busy = ! empty($answer['deferred']) || ! empty($answer['rate_limited']) || ! empty($answer['retry_later']);

        return $busy && (int) ($answer['retry_after'] ?? 0) <= 0;
    }

    /**
     * Rest $courier in this lane and return the rest in seconds.
     */
    private function restCourier(string $courier): int
    {
        try {
            $strikesKey = 'bulk_waiting_lane:busy:'.$courier;
            Cache::add($strikesKey, 0, now()->addHour());
            $strikes = max(1, (int) Cache::increment($strikesKey));
            $seconds = (int) min(self::MAX_REST_SECONDS, $this->retryGap() * 2 ** min(10, $strikes - 1));
            $until = $this->clock() + $seconds;

            if ($until > (int) Cache::get('bulk_waiting_lane:rest:'.$courier, 0)) {
                Cache::put('bulk_waiting_lane:rest:'.$courier, $until, now()->addSeconds($seconds + 5));
            }

            return $seconds;
        } catch (\Throwable) {
            return $this->retryGap();
        }
    }

    private function laneRestSeconds(string $courier): int
    {
        try {
            return max(0, (int) Cache::get('bulk_waiting_lane:rest:'.$courier, 0) - $this->clock());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function noteAsked(string $courier): void
    {
        try {
            $key = 'bulk_waiting_lane:asking:'.$courier;
            $now = $this->clock();
            $asking = Cache::get($key);

            // A courier the lane stopped asking for two minutes starts a new measurement.
            $since = is_array($asking) && $now - (int) ($asking['seen'] ?? 0) <= 120
                ? (int) ($asking['since'] ?? $now)
                : $now;

            Cache::put($key, ['since' => $since, 'seen' => $now], now()->addMinutes(10));
        } catch (\Throwable) {
            //
        }
    }

    private function noteAnswered(string $courier): void
    {
        try {
            $bucket = 'bulk_waiting_lane:answers:'.$courier.':'.intdiv($this->clock(), 60);
            Cache::add($bucket, 0, now()->addMinutes(self::RATE_MINUTES + 5));
            Cache::increment($bucket);
            Cache::forget('bulk_waiting_lane:busy:'.$courier);
            Cache::forget('bulk_waiting_lane:rest:'.$courier);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Answers per minute the lane got from $courier lately, or null while
     * it has been asking for less than two minutes.
     */
    private function measuredRate(string $courier): ?float
    {
        try {
            $asking = Cache::get('bulk_waiting_lane:asking:'.$courier);
            $now = $this->clock();

            if (! is_array($asking) || $now - (int) ($asking['seen'] ?? 0) > 120) {
                return null;
            }

            $span = min(self::RATE_MINUTES * 60, $now - (int) ($asking['since'] ?? $now));

            if ($span < 120) {
                return null;
            }

            $answers = 0;

            for ($minute = intdiv($now - $span, 60); $minute <= intdiv($now, 60); $minute++) {
                $answers += (int) Cache::get('bulk_waiting_lane:answers:'.$courier.':'.$minute, 0);
            }

            return $answers / ($span / 60);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{per_minute: float, resting_seconds: int}
     */
    private function paceStatus(string $courier): array
    {
        try {
            $status = match ($courier) {
                'pathao' => app(PathaoFraudChecker::class)->paceStatus(),
                'paperfly' => app(PaperflyFraudChecker::class)->paceStatus(),
                default => ['per_minute' => 60.0, 'resting_seconds' => 0],
            };
        } catch (\Throwable) {
            $status = ['per_minute' => 0.0, 'resting_seconds' => 0];
        }

        return [
            'per_minute' => (float) ($status['per_minute'] ?? 0),
            'resting_seconds' => (int) ($status['resting_seconds'] ?? 0),
        ];
    }

    /**
     * Seconds before the next ask. A courier that had no room, asked us to
     * slow down, or is paused says when; a courier that failed waits the
     * retry gap, doubling with each try.
     *
     * @param  array<string, mixed>  $courierReport
     */
    private function delay(array $courierReport, int $tries): int
    {
        if (! empty($courierReport['deferred']) || ! empty($courierReport['rate_limited']) || ! empty($courierReport['retry_later'])) {
            $retryAfter = (int) ($courierReport['retry_after'] ?? 0);

            return max(5, $retryAfter > 0 ? $retryAfter : $this->retryGap());
        }

        $gap = $this->retryGap();

        return min(30, $gap * (2 ** max(0, $tries - 1)));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function expired(array $entry, int $now): bool
    {
        if ((int) ($entry['tries'] ?? 0) >= $this->maxTries()) {
            return true;
        }

        $minutes = max(10, (int) config('fraud_check.bulk_waiting.max_minutes', 360));

        return $now - (int) ($entry['since'] ?? $now) > $minutes * 60;
    }

    /**
     * @param  array<string, array<string, mixed>>  $waiting
     */
    private function nextAt(array $waiting): ?int
    {
        if ($waiting === []) {
            return null;
        }

        return min(array_map(fn (array $entry): int => (int) ($entry['next_at'] ?? 0), $waiting));
    }

    /**
     * @param  list<mixed>  $rows
     * @return array<string, int>
     */
    private function waitingCounts(array $rows): array
    {
        $counts = [];

        foreach ($rows as $waiting) {
            if (is_string($waiting)) {
                $waiting = json_decode($waiting, true);
            }

            if (! is_array($waiting)) {
                continue;
            }

            foreach (array_keys($waiting) as $courier) {
                $counts[$courier] = ($counts[$courier] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    private function replaceCourierReport(array $report, string $courier, array $live): array
    {
        foreach ($report['courier'] ?? [] as $index => $entry) {
            if (is_array($entry) && MerchantBulkFraudRow::courierKeyFromTitle((string) ($entry['title'] ?? '')) === $courier) {
                $report['courier'][$index]['report'] = $live;

                break;
            }
        }

        return $report;
    }

    /**
     * @return list<mixed>
     */
    private static function stripFrauds(mixed $frauds): array
    {
        if (! is_array($frauds)) {
            return [];
        }

        $kept = [];

        foreach ($frauds as $fraud) {
            if (is_string($fraud)) {
                $kept[] = $fraud;
            } elseif (is_array($fraud)) {
                $kept[] = array_intersect_key($fraud, array_flip(['details', 'note', 'type', 'count', 'courier']));
            }
        }

        return $kept;
    }

    private function beat(): void
    {
        try {
            Cache::put(self::HEARTBEAT_KEY, $this->clock(), now()->addMinutes(10));
        } catch (\Throwable) {
            //
        }
    }

    private function clock(): int
    {
        return now()->getTimestamp();
    }

    private function today(): Carbon
    {
        return Carbon::now('Asia/Dhaka');
    }
}
