<?php

namespace App\Services\OrderIntelligence;

use App\Jobs\OrderIntelligence\RefreshCourierSnapshotsJob;
use App\Models\AccessToken;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Services\Courier\CourierAccountService;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheckService;
use Illuminate\Http\Request;

class FraudCheckCoordinator
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private FraudCheckIngestor $fraudCheckIngestor,
        private PlatformIntelligenceReader $platformIntelligenceReader,
        private PlatformSufficiencyChecker $sufficiencyChecker,
        private LegacyReportFormatter $legacyReportFormatter,
        private CourierAccountService $courierAccountService,
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
            'phone' => $phoneNormalized,
            'mode' => $mode,
            'stale_while_revalidate' => (bool) config('order_intelligence.fraud_check.stale_while_revalidate', true),
            'max_snapshot_staleness_hours' => (int) config('order_intelligence.fraud_check.max_snapshot_staleness_hours', 5),
            'partial_refresh' => (bool) config('order_intelligence.fraud_check.partial_refresh', true),
            'preserve_snapshot_on_failure' => (bool) config('order_intelligence.fraud_check.preserve_snapshot_on_failure', true),
        ]);

        $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessToken?->id);
        $snapshotSummary = $this->summarizeSnapshots($platformData);
        $log->add('cache_lookup', $platformData ? 'Found platform intelligence for phone' : 'No platform cache for phone', [
            'has_platform_data' => $platformData !== null,
            'snapshots' => $snapshotSummary,
        ]);

        $usePlatform = $this->sufficiencyChecker->shouldUsePlatform($platformData);
        $log->add('decision', $usePlatform
            ? 'Serving from platform cache (skip live courier calls for response)'
            : 'Cache insufficient — running live courier checks', [
            'should_use_platform' => $usePlatform,
            'mode' => $mode,
        ]);

        if ($usePlatform) {
            $report = $this->legacyReportFormatter->format($platformData);
            $this->fraudCheckIngestor->ingestOrderContext($context);

            $pathaoUpgraded = false;
            if ($this->pathaoReportNeedsCountUpgrade($report)) {
                $log->add('pathao_upgrade', 'Pathao rating-only in cache — live Pathao fetch for delivery counts');
                $before = $report;
                $report = $this->upgradePathaoCountsInReport(
                    $report,
                    $phoneNormalized,
                    $accessToken,
                    $payload,
                    $log,
                );
                $pathaoUpgraded = $report !== $before
                    && ! $this->pathaoReportNeedsCountUpgrade($report);
            }

            $needRefresh = $this->sufficiencyChecker->couriersNeedingRefresh($platformData);
            if ($pathaoUpgraded) {
                $needRefresh = array_values(array_filter(
                    $needRefresh,
                    static fn (string $courier): bool => $courier !== 'pathao',
                ));
            }
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

            return $this->withDebug($report, $log, $attachDebug);
        }

        $log->add('live_fetch', 'Calling courier partners live');
        $report = $this->fraudCheckService->getReport($phone);
        $report['source'] = $platformData ? 'hybrid' : 'external';
        $report = $this->mergeLastGoodCourierData($report, $phoneNormalized, $log);

        $this->ingestExternalReport($accessToken, $payload, $report);
        $log->add('persist', 'Ingested live courier results into platform snapshots');

        return $this->withDebug(
            $this->attachPlatformData($report, $phoneNormalized, $accessToken?->id),
            $log,
            $attachDebug,
        );
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
            if (! is_array($item) || empty($item['phone'])) {
                continue;
            }

            $itemPayload = [...$item, 'phone' => (string) $item['phone']];
            $users[] = [
                ...$item,
                'report' => $this->checkSingle($request, $itemPayload),
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

            $live = $entry['report'];
            if (! $this->isFailedLiveCourierReport($live)) {
                continue;
            }

            /** @var CourierCustomerSnapshot $snapshot */
            $snapshot = $snapshots->get($key);
            if (! $this->snapshotHasUsefulData($snapshot)) {
                continue;
            }

            $failureMessage = (string) ($live['message'] ?? 'Courier temporarily unavailable.');
            $merged = CourierReportFormatter::fromCounts(
                (int) $snapshot->confirmed,
                (int) $snapshot->cancel,
                array_filter([
                    'total_order' => (int) $snapshot->total_order,
                    'success_rate' => $snapshot->success_rate,
                    'customer_rating' => $snapshot->customer_rating,
                    'frauds_count' => (int) $snapshot->frauds_count,
                    'frauds' => is_array($live['frauds'] ?? null) ? $live['frauds'] : [],
                    'status' => 'degraded',
                    'unavailable' => true,
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
        return (int) $snapshot->total_order > 0
            || (int) $snapshot->confirmed > 0
            || (int) $snapshot->cancel > 0
            || (int) $snapshot->frauds_count > 0
            || filled($snapshot->customer_rating);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function pathaoReportNeedsCountUpgrade(array $report): bool
    {
        foreach ($report['courier'] ?? [] as $entry) {
            if (! is_array($entry) || ! is_array($entry['report'] ?? null)) {
                continue;
            }

            $title = strtolower(trim((string) ($entry['title'] ?? '')));
            if (! str_contains($title, 'pathao')) {
                continue;
            }

            return CourierReportFormatter::isRatingOnly($entry['report']);
        }

        return false;
    }

    /**
     * When cache only has Pathao rating (Good Customer / Rating only), try live sources
     * that may still return delivery counts (Hermes), then rebuild aggregates.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function upgradePathaoCountsInReport(
        array $report,
        string $phoneNormalized,
        ?AccessToken $accessToken,
        array $payload,
        FraudCheckDecisionLog $log,
    ): array {
        try {
            $livePathao = $this->fraudCheckService->checkCourier('pathao', $phoneNormalized);
        } catch (\Throwable $e) {
            $log->add('pathao_upgrade', 'Live Pathao upgrade failed', [
                'message' => $e->getMessage(),
            ]);

            return $report;
        }

        if (! CourierReportFormatter::hasDeliveryCounts($livePathao)) {
            $log->add('pathao_upgrade', 'Live Pathao still rating-only or empty — keeping cache row', [
                'data_type' => $livePathao['data_type'] ?? null,
                'status' => $livePathao['status'] ?? null,
            ]);

            return $report;
        }

        $livePathao['source'] = 'live_upgrade';
        $livePathao['from_cache'] = false;
        unset($livePathao['cache_label']);

        $courier = $report['courier'] ?? [];
        foreach ($courier as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $title = strtolower(trim((string) ($entry['title'] ?? '')));
            if (! str_contains($title, 'pathao')) {
                continue;
            }
            $courier[$index]['report'] = $livePathao;
            break;
        }

        $report['courier'] = $courier;
        $report = $this->recalculateTotalsFromCourier($report);

        // Persist upgraded Pathao so the next plugin hit is not rating-only.
        try {
            $this->ingestExternalReport($accessToken, [
                ...$payload,
                'phone' => $phoneNormalized,
            ], $report);
            $log->add('pathao_upgrade', 'Upgraded Pathao to delivery counts and persisted snapshot', [
                'total_order' => $livePathao['total_order'] ?? 0,
                'confirmed' => $livePathao['confirmed'] ?? 0,
                'cancel' => $livePathao['cancel'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            $log->add('pathao_upgrade', 'Pathao counts upgraded in response but persist failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return $report;
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
        $report['success_rate'] = $totalOrder > 0
            ? ceil(($confirmOrder / $totalOrder) * 100).'%'
            : ($report['success_rate'] ?? 'No order history found!');

        return $report;
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
