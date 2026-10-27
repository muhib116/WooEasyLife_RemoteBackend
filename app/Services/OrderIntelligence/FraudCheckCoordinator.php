<?php

namespace App\Services\OrderIntelligence;

use App\Jobs\OrderIntelligence\RefreshCourierSnapshotsJob;
use App\LogHelper;
use App\Models\AccessToken;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Services\Courier\CourierAccountService;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\CourierTransport;
use App\Services\FraudCheck\MerchantBulkDailySync;
use App\Services\FraudCheck\RedxStaticSample;
use App\Services\FraudCheckService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FraudCheckCoordinator
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private FraudCheckIngestor $fraudCheckIngestor,
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private PlatformSufficiencyChecker $sufficiencyChecker,
        private LegacyReportFormatter $legacyReportFormatter,
        private CourierAccountService $courierAccountService,
        private CourierIntelPersister $courierIntelPersister,
        private MerchantBulkDailySync $merchantBulkDailySync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkSingle(Request $request, array $payload): array
    {
        $attachDebug = $this->shouldAttachDebug($request, $payload);
        $writeLog = (bool) config('order_intelligence.fraud_check.debug_trace', false);
        $log = new FraudCheckDecisionLog(
            enabled: $attachDebug || $writeLog,
            writeLaravelLog: $writeLog,
        );

        $phone = (string) ($payload['phone'] ?? '');
        $accessToken = $this->courierAccountService->resolveAccessToken($request);
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);
        $context = $this->fraudCheckIngestor->resolveContextFromToken($accessToken, $payload);
        $mode = (string) config('order_intelligence.fraud_check.mode', 'hybrid');

        $log->add('start', 'Fraud check started', [
            'phone' => CourierTransport::maskPhone($phoneNormalized),
            'mode' => $mode,
            'stale_while_revalidate' => (bool) config('order_intelligence.fraud_check.stale_while_revalidate', true),
            'max_snapshot_staleness_hours' => (int) config('order_intelligence.fraud_check.max_snapshot_staleness_hours', 10),
            'partial_refresh' => (bool) config('order_intelligence.fraud_check.partial_refresh', true),
            'preserve_snapshot_on_failure' => (bool) config('order_intelligence.fraud_check.preserve_snapshot_on_failure', true),
        ]);

        $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessToken?->id);
        $snapshotSummary = $this->summarizeSnapshots($platformData);
        $log->add('cache_lookup', $platformData ? 'Found platform intelligence for phone' : 'No platform cache for phone', [
            'has_platform_data' => $platformData !== null,
            'snapshots' => $snapshotSummary,
        ]);

        $forceLive = filter_var($payload['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $usePlatform = ! $forceLive && $this->sufficiencyChecker->shouldUsePlatform($platformData);
        $log->add('decision', $forceLive
            ? 'Row refresh requested — running live courier checks'
            : ($usePlatform
                ? 'Serving from platform cache (skip live courier calls for response)'
                : 'Cache insufficient — running live courier checks'), [
            'should_use_platform' => $usePlatform,
            'refresh' => $forceLive,
            'mode' => $mode,
        ]);

        if ($usePlatform) {
            $report = $this->legacyReportFormatter->format($platformData);
            $this->fraudCheckIngestor->ingestOrderContext($context);

            $filledNow = $this->fillBlockingCouriers(
                $report,
                $this->sufficiencyChecker->couriersBlockingTheResponse($platformData),
                $phoneNormalized,
                $accessToken,
                $payload,
                $log,
            );
            $report = $filledNow['report'];

            $needRefresh = array_values(array_filter(
                $this->sufficiencyChecker->couriersNeedingRefresh($platformData),
                static fn (string $courier): bool => ! in_array($courier, $filledNow['couriers'], true),
            ));
            if ($needRefresh !== []) {
                $queue = (string) config('queue.default', 'sync');
                $log->add('background_refresh', 'Scheduled background refresh for stale/failed couriers', [
                    'couriers' => $needRefresh,
                    'via' => $queue === 'sync' ? 'afterResponse' : 'queue:'.$queue,
                ]);
                $this->scheduleSnapshotRefresh($phoneNormalized, $accessToken?->id, $needRefresh);
            } else {
                $log->add('background_refresh', 'All courier snapshots are fresh — no refresh scheduled', [
                    'couriers' => [],
                ]);
            }

            $this->syncDailyResultIfPresent($phoneNormalized, $report);

            return $this->withDebug($report, $log, $attachDebug);
        }

        $log->add('live_fetch', 'Calling courier partners live');
        $report = $this->fraudCheckService->getReport($phone);
        $report['source'] = $platformData ? 'hybrid' : 'external';
        $report = $this->mergeLastGoodCourierData($report, $phoneNormalized, $log);

        $this->ingestExternalReport($accessToken, $payload, $report);
        $log->add('persist', 'Ingested live courier results into platform snapshots');

        $enriched = $this->attachPlatformData($report, $phoneNormalized, $accessToken?->id);
        $this->syncDailyResultIfPresent($phoneNormalized, $enriched);

        return $this->withDebug(
            $enriched,
            $log,
            $attachDebug,
        );
    }

    /**
     * Ask the named couriers once more and put each answer into the report.
     * The merchant bulk check calls this when Pathao or another core courier
     * did not answer, so the couriers that already answered are not called again.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     * @param  list<string>  $couriers
     * @return array<string, mixed>
     */
    public function retryCouriers(Request $request, array $payload, array $report, array $couriers): array
    {
        return $this->askCouriers($request, $payload, $report, $couriers)['report'];
    }

    /**
     * Ask only the named couriers and put each answer into the report. Also
     * returns what every asked courier said, so a caller can tell a courier
     * that was not asked yet from one that was asked and failed.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     * @param  list<string>  $couriers
     * @return array{report: array<string, mixed>, answered: list<string>, live: array<string, array<string, mixed>>}
     */
    public function askCouriers(Request $request, array $payload, array $report, array $couriers): array
    {
        if ($couriers === []) {
            return ['report' => $report, 'answered' => [], 'live' => []];
        }

        $writeLog = (bool) config('order_intelligence.fraud_check.debug_trace', false);
        $log = new FraudCheckDecisionLog(enabled: $writeLog, writeLaravelLog: $writeLog);
        $phoneNormalized = $this->fraudCheckService->normalizePhone((string) ($payload['phone'] ?? ''));
        $accessToken = $this->courierAccountService->resolveAccessToken($request);

        $filled = $this->fillBlockingCouriers($report, $couriers, $phoneNormalized, $accessToken, $payload, $log);

        foreach ($couriers as $courier) {
            // A courier that was not asked, or asked us to slow down, did not fail.
            $live = $filled['live'][$courier] ?? [];
            if (! empty($live['deferred']) || ! empty($live['rate_limited'])) {
                continue;
            }

            FraudCheckService::noteCourierRetry($courier, in_array($courier, $filled['couriers'], true));
        }

        if ($filled['couriers'] === []) {
            return ['report' => $report, 'answered' => [], 'live' => $filled['live']];
        }

        $this->syncDailyResultIfPresent($phoneNormalized, $filled['report']);

        return ['report' => $filled['report'], 'answered' => $filled['couriers'], 'live' => $filled['live']];
    }

    /**
     * Debug trail is admin-web only. Never attach `_debug` to plugin/public API responses.
     *
     * @param  array<string, mixed>  $payload
     */
    private function shouldAttachDebug(Request $request, array $payload): bool
    {
        if ($request->is('api/*')) {
            return false;
        }

        $user = $request->user();
        if (! $user || ($user->role ?? null) !== 'admin') {
            return false;
        }

        if (filter_var($payload['debug'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return (bool) config('order_intelligence.fraud_check.debug_trace', false);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function withDebug(array $report, FraudCheckDecisionLog $log, bool $attach): array
    {
        if (! $attach) {
            return $report;
        }

        $report['_debug'] = $log->toArray();

        return $report;
    }

    /**
     * @param  array<string, mixed>|null  $platformData
     * @return list<array<string, mixed>>
     */
    private function summarizeSnapshots(?array $platformData): array
    {
        if ($platformData === null) {
            return [];
        }

        $out = [];
        foreach ($platformData['courier_stats'] ?? [] as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }

            $out[] = [
                'courier' => $snapshot['courier'] ?? null,
                'total_order' => (int) ($snapshot['total_order'] ?? 0),
                'fetched_at' => $snapshot['fetched_at'] ?? null,
                'fetch_failed' => ! empty($snapshot['fetch_failed']),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function checkMultiple(Request $request, array $items): array
    {
        $users = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (empty($item['phone'])) {
                $users[] = [
                    ...$item,
                    'report' => [
                        'total_order' => 0,
                        'confirmed' => 0,
                        'frauds' => [],
                        'cancel' => 0,
                        'success_rate' => 'No order history found!',
                        'courier' => [],
                        'error' => 'Missing phone',
                    ],
                ];

                continue;
            }

            $itemPayload = [...$item, 'phone' => (string) $item['phone']];

            try {
                $report = $this->checkSingle($request, $itemPayload);
            } catch (InvalidArgumentException $e) {
                $report = [
                    'total_order' => 0,
                    'confirmed' => 0,
                    'frauds' => [],
                    'cancel' => 0,
                    'success_rate' => 'No order history found!',
                    'courier' => [],
                    'error' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                LogHelper::saveLog('Fraud check multiple item error', CourierTransport::safeMessage($e));
                $report = [
                    'total_order' => 0,
                    'confirmed' => 0,
                    'frauds' => [],
                    'cancel' => 0,
                    'success_rate' => 'No order history found!',
                    'courier' => [],
                    'error' => 'Unable to complete fraud check.',
                ];
            }

            $users[] = [
                ...$item,
                'report' => $report,
            ];
        }

        return $users;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $report
     */
    private function ingestExternalReport(?AccessToken $accessToken, array $payload, array $report): void
    {
        $context = $this->fraudCheckIngestor->resolveContextFromToken($accessToken, $payload);
        $couriers = $this->extractCourierReportsByTitle($report);

        $steadfast = $couriers['steadfast'];
        $pathao = $couriers['pathao'];
        $paperfly = $couriers['paperfly'];
        $redx = $couriers['redx'];
        $carrybee = $couriers['carrybee'];

        if (! empty($report['frauds']) && empty($steadfast['frauds'])) {
            $steadfast['frauds'] = $report['frauds'];
        }

        $this->fraudCheckIngestor->ingest(
            $context,
            $steadfast,
            $pathao,
            $paperfly,
            $redx,
            $carrybee,
        );
    }

    /**
     * Prefer the real queue so HTTP responses are not blocked (important on php artisan serve).
     * Fall back to afterResponse when QUEUE_CONNECTION=sync.
     *
     * @param  list<string>  $onlyCouriers
     */
    private function scheduleSnapshotRefresh(string $phoneNormalized, ?int $accessTokenId, array $onlyCouriers): void
    {
        if ($onlyCouriers === []) {
            return;
        }

        $queue = (string) config('queue.default', 'sync');

        if ($queue !== 'sync') {
            RefreshCourierSnapshotsJob::dispatch($phoneNormalized, $accessTokenId, $onlyCouriers);

            return;
        }

        dispatch(function () use ($phoneNormalized, $accessTokenId, $onlyCouriers) {
            app(CourierSnapshotRefresher::class)->refresh($phoneNormalized, $accessTokenId, $onlyCouriers);
        })->afterResponse();
    }

    /**
     * When a live courier call fails, keep last known good counts in the API response
     * (DB already preserves them; the response must match).
     *
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function mergeLastGoodCourierData(array $report, string $phoneNormalized, ?FraudCheckDecisionLog $log = null): array
    {
        if (! config('order_intelligence.fraud_check.preserve_snapshot_on_failure', true)) {
            $log?->add('fallback', 'Preserve snapshot on failure is off — live courier result kept');

            return $report;
        }

        $snapshots = CourierCustomerSnapshot::query()
            ->where('phone_normalized', $phoneNormalized)
            ->get()
            ->keyBy('courier');

        if ($snapshots->isEmpty()) {
            $log?->add('fallback', 'No stored snapshots available for live-failure fallback');

            return $report;
        }

        $courier = $report['courier'] ?? [];
        $changed = false;
        $mergedCouriers = [];

        foreach ($courier as $index => $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $title = strtolower(trim((string) ($entry['title'] ?? '')));
            $key = match (true) {
                str_contains($title, 'stead') => 'steadfast',
                str_contains($title, 'pathao') => 'pathao',
                str_contains($title, 'paper') => 'paperfly',
                str_contains($title, 'redx') || str_contains($title, 'red x') => 'redx',
                str_contains($title, 'carry') => 'carrybee',
                default => null,
            };

            if ($key === null || ! $snapshots->has($key)) {
                continue;
            }

            /** @var CourierCustomerSnapshot $snapshot */
            $snapshot = $snapshots->get($key);
            if (! $this->snapshotHasUsefulData($snapshot)) {
                continue;
            }

            $live = $entry['report'];
            $failedLive = $this->isFailedLiveCourierReport($live);
            $degradedCounts = ! $failedLive
                && $this->snapshotHasDeliveryCounts($snapshot)
                && $this->isDegradedReportWithoutCounts($live);

            if (! $failedLive && ! $degradedCounts) {
                continue;
            }

            $rawSnapshot = is_array($snapshot->raw_report) ? $snapshot->raw_report : [];
            $snapshotFrauds = is_array($rawSnapshot['frauds'] ?? null) ? $rawSnapshot['frauds'] : [];
            $liveFrauds = is_array($live['frauds'] ?? null) ? $live['frauds'] : [];
            $partial = max(0, (int) ($rawSnapshot['partial'] ?? 0));

            $failureMessage = (string) ($live['message'] ?? 'Courier temporarily unavailable.');
            $merged = CourierReportFormatter::fromCounts(
                (int) $snapshot->confirmed,
                (int) $snapshot->cancel,
                array_filter([
                    'total_order' => (int) $snapshot->total_order,
                    'partial' => $partial > 0 ? $partial : null,
                    'success_rate' => $snapshot->success_rate,
                    'customer_rating' => $snapshot->customer_rating,
                    'frauds_count' => (int) $snapshot->frauds_count,
                    'frauds' => $liveFrauds !== [] ? $liveFrauds : $snapshotFrauds,
                    'status' => $failedLive ? 'degraded' : 'ok',
                    'unavailable' => $failedLive ? true : null,
                    'api_success' => ! $failedLive ? true : null,
                    'source' => 'platform_cache',
                    'message' => $failureMessage.' Showing last known good data.',
                    'cached_fallback' => true,
                ], fn ($value) => $value !== null),
            );

            $courier[$index]['report'] = $merged;
            $changed = true;
            $mergedCouriers[] = $key;
        }

        if (! $changed) {
            $log?->add('fallback', 'Live courier results used as-is (no failed→cache merges)');

            return $report;
        }

        $log?->add('fallback', 'Merged last-good cache into failed live couriers', [
            'couriers' => $mergedCouriers,
        ]);

        $report['courier'] = $courier;

        return $this->recalculateTotalsFromCourier($report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function isFailedLiveCourierReport(array $report): bool
    {
        if (! empty($report['unavailable']) || ! empty($report['credential_error']) || ! empty($report['fetch_failed'])) {
            return true;
        }

        if (($report['status'] ?? null) === 'unavailable') {
            return true;
        }

        $message = strtolower((string) ($report['message'] ?? ''));

        foreach (['session expired', 'curl error', 'connection reset', 'login failed', 'token expired', 'http 404', 'http 5'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function snapshotHasUsefulData(CourierCustomerSnapshot $snapshot): bool
    {
        if (RedxStaticSample::matchesSnapshot($snapshot)) {
            return false;
        }

        return (int) $snapshot->total_order > 0
            || (int) $snapshot->confirmed > 0
            || (int) $snapshot->cancel > 0
            || (int) $snapshot->frauds_count > 0
            || filled($snapshot->customer_rating);
    }

    private function snapshotHasDeliveryCounts(CourierCustomerSnapshot $snapshot): bool
    {
        if (RedxStaticSample::matchesSnapshot($snapshot)) {
            return false;
        }

        return (int) $snapshot->total_order > 0
            || (int) $snapshot->confirmed > 0
            || (int) $snapshot->cancel > 0;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function isDegradedReportWithoutCounts(array $report): bool
    {
        if (CourierReportFormatter::hasDeliveryCounts($report)) {
            return false;
        }

        if (CourierReportFormatter::isRatingOnly($report)) {
            return true;
        }

        return ! empty($report['counts_hidden']) && empty($report['history_unknown']);
    }

    /**
     * Fetch couriers that are missing, failed, or Pathao rating-only before
     * the response goes out. check() creates a session when one is not saved.
     *
     * @param  array<string, mixed>  $report
     * @param  list<string>  $couriers
     * @param  array<string, mixed>  $payload
     * @return array{report: array<string, mixed>, couriers: list<string>, live: array<string, array<string, mixed>>}
     */
    private function fillBlockingCouriers(
        array $report,
        array $couriers,
        string $phoneNormalized,
        ?AccessToken $accessToken,
        array $payload,
        FraudCheckDecisionLog $log,
    ): array {
        if ($couriers === []) {
            return ['report' => $report, 'couriers' => [], 'live' => []];
        }

        $log->add('live_fill', 'Fetching couriers that did not answer before sending the result', [
            'couriers' => $couriers,
        ]);

        $saved = [
            'steadfast' => [],
            'pathao' => [],
            'paperfly' => [],
            'redx' => [],
            'carrybee' => [],
        ];
        $filled = [];
        $answers = [];

        foreach ($couriers as $courier) {
            try {
                $live = $this->fraudCheckService->checkCourier($courier, $phoneNormalized);
            } catch (\Throwable $exception) {
                $log->add('live_fill', 'Courier fill failed', [
                    'courier' => $courier,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! is_array($live)) {
                continue;
            }

            $answers[$courier] = $live;

            // Keep the cache row only when live Pathao/etc. still did not answer.
            // Rating-only and real counts both replace "Data unavailable".
            if (
                ! empty($live['unavailable'])
                && ! CourierReportFormatter::hasDeliveryCounts($live)
                && ! CourierReportFormatter::isRatingOnly($live)
                && empty($live['api_success'])
            ) {
                $log->add('live_fill', 'Courier still unavailable after live fill', [
                    'courier' => $courier,
                ]);

                continue;
            }

            $live['source'] = 'live_fill';
            $live['from_cache'] = false;
            $saved[$courier] = $live;
            $report = $this->replaceCourierReport($report, $courier, $live);
            $filled[] = $courier;
        }

        $report = $this->recalculateTotalsFromCourier($report);
        $this->persistFilledCouriers($phoneNormalized, $accessToken?->id, $saved);

        // Only couriers that actually answered are done. A failed fill stays
        // eligible for the background refresh.
        return ['report' => $report, 'couriers' => $filled, 'live' => $answers];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    private function replaceCourierReport(array $report, string $courier, array $live): array
    {
        $needle = match ($courier) {
            'steadfast' => 'stead',
            'paperfly' => 'paper',
            'redx' => 'red',
            'carrybee' => 'carry',
            default => $courier,
        };

        foreach ($report['courier'] ?? [] as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $title = strtolower(trim((string) ($entry['title'] ?? '')));
            if (! str_contains($title, $needle)) {
                continue;
            }

            $report['courier'][$index]['report'] = $live;

            break;
        }

        return $report;
    }

    /**
     * @param  array<string, array<string, mixed>>  $saved
     */
    private function persistFilledCouriers(string $phoneNormalized, ?int $accessTokenId, array $saved): void
    {
        if ($saved === [] || ! array_filter($saved)) {
            return;
        }

        $customer = PlatformCustomer::query()->where('phone_normalized', $phoneNormalized)->first();

        if ($customer === null) {
            return;
        }

        try {
            $this->courierIntelPersister->persistFromFraudCheckReports(
                customer: $customer,
                phoneNormalized: $phoneNormalized,
                steadfastReport: $saved['steadfast'] ?? [],
                pathaoReport: $saved['pathao'] ?? [],
                paperflyReport: $saved['paperfly'] ?? [],
                redxReport: $saved['redx'] ?? [],
                carrybeeReport: $saved['carrybee'] ?? [],
                sourceAccessTokenId: $accessTokenId,
            );
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Fraud check courier fill persist failed', $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function recalculateTotalsFromCourier(array $report): array
    {
        $aggregate = [];
        $carrybeeFraudsCount = 0;

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $courierReport = $entry['report'];
            $title = strtolower(trim((string) ($entry['title'] ?? '')));

            if (str_contains($title, 'carry')) {
                $carrybeeFraudsCount = (int) ($courierReport['frauds_count'] ?? 0);
            }

            if (str_contains($title, 'redx') && ! config('fraud_check.aggregate_redx', true)) {
                continue;
            }

            if (str_contains($title, 'carry') && ! config('fraud_check.aggregate_carrybee', true)) {
                continue;
            }

            $aggregate[] = $courierReport;
        }

        $totals = CourierReportFormatter::aggregateTotals(...$aggregate);
        $totalOrder = (int) ceil($totals['total_order']);
        $confirmOrder = (int) ceil($totals['confirmed']);
        $cancelOrder = (int) ceil($totals['cancel']);

        $report['total_order'] = $totalOrder;
        $report['confirmed'] = $confirmOrder;
        $report['cancel'] = $cancelOrder;
        $report['carrybee_frauds_count'] = $carrybeeFraudsCount;
        $report['success_rate'] = $this->resolveSuccessRateFromCourierEntries(
            $totalOrder,
            $confirmOrder,
            $report['courier'] ?? [],
        );

        return $report;
    }

    /**
     * @param  array<int, mixed>  $courierEntries
     */
    private function resolveSuccessRateFromCourierEntries(int $totalOrder, int $confirmOrder, array $courierEntries): string
    {
        if ($totalOrder > 0) {
            return ceil(($confirmOrder / $totalOrder) * 100).'%';
        }

        foreach ($courierEntries as $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $courierReport = $entry['report'];

            if (($courierReport['data_type'] ?? 'delivery') === 'fraud_reports') {
                continue;
            }

            if (! empty($courierReport['customer_rating'])) {
                return CourierReportFormatter::formatRating((string) $courierReport['customer_rating']);
            }

            $rate = (string) ($courierReport['success_rate'] ?? '');

            if ($rate !== '' && $rate !== 'No order history found!') {
                return $rate;
            }
        }

        return 'No order history found!';
    }

    /**
     * Keep today's BulkFraudDailyResult row in sync when a phone number is checked
     * or refreshed via Admin, Plugin, or Portal so merchant and admin views never diverge.
     *
     * @param  array<string, mixed>  $report
     */
    private function syncDailyResultIfPresent(string $phoneNormalized, array $report): void
    {
        $this->merchantBulkDailySync->syncIfPresent($phoneNormalized, $report);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function attachPlatformData(array $report, string $phoneNormalized, ?int $accessTokenId): array
    {
        try {
            $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);

            if ($platformData === null) {
                return $report;
            }

            $report['platform_intelligence'] = $platformData['platform_intelligence'] ?? null;
            $report['your_store'] = $platformData['your_store'] ?? null;
            $report['courier_fraud_notes'] = $platformData['courier_fraud_notes'] ?? [];

            if (! empty($platformData['courier_fraud_notes'])) {
                $report['frauds'] = array_map(
                    fn (array $note) => [
                        'name' => $note['name'] ?? null,
                        'details' => $note['details'] ?? '',
                        'consignment_id' => $note['consignment_id'] ?? null,
                        'created_at' => $note['created_at'] ?? null,
                        'courier' => $note['courier'] ?? null,
                    ],
                    $platformData['courier_fraud_notes'],
                );
            }
        } catch (\Throwable) {
            // Keep external report if platform enrichment fails.
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{
     *     steadfast: array<string, mixed>,
     *     pathao: array<string, mixed>,
     *     paperfly: array<string, mixed>,
     *     redx: array<string, mixed>,
     *     carrybee: array<string, mixed>
     * }
     */
    private function extractCourierReportsByTitle(array $report): array
    {
        $mapped = [
            'steadfast' => [],
            'pathao' => [],
            'paperfly' => [],
            'redx' => [],
            'carrybee' => [],
        ];

        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $title = strtolower(trim((string) ($entry['title'] ?? '')));

            $key = match (true) {
                str_contains($title, 'stead') => 'steadfast',
                str_contains($title, 'pathao') => 'pathao',
                str_contains($title, 'paper') => 'paperfly',
                str_contains($title, 'redx') || str_contains($title, 'red x') => 'redx',
                str_contains($title, 'carry') => 'carrybee',
                default => null,
            };

            if ($key !== null) {
                $mapped[$key] = $entry['report'];
            }
        }

        return $mapped;
    }
}
