<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\MerchantCustomerStats;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Services\FraudCheck\RedxStaticSample;

class PlatformIntelligenceReader
{
    public function __construct(
        private IntelligenceCache $cache,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forPhone(string $phoneNormalized, ?int $accessTokenId = null): ?array
    {
        $cached = $this->cache->get($phoneNormalized, $accessTokenId);

        if ($cached !== null) {
            return $cached;
        }

        $stats = PlatformCustomerStats::query()
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        if (! $stats) {
            return null;
        }

        $payload = [
            'phone' => $phoneNormalized,
            'platform_intelligence' => [
                'counts' => $stats->counts ?? [],
                'rates' => $stats->rates ?? [],
                'total_orders' => $stats->total_orders,
                'total_merchants' => $stats->total_merchants,
                'risk_tier' => $stats->risk_tier,
                'risk_score' => $stats->risk_score,
                'data_freshness' => optional($stats->stats_computed_at)?->toIso8601String(),
            ],
            'courier_stats' => $this->courierSnapshots($phoneNormalized),
            'courier_fraud_notes' => $this->fraudNotes($phoneNormalized),
        ];

        if ($accessTokenId !== null) {
            $merchantStats = MerchantCustomerStats::query()
                ->where('phone_normalized', $phoneNormalized)
                ->where('access_token_id', $accessTokenId)
                ->first();

            $payload['your_store'] = $merchantStats ? [
                'total_orders' => $merchantStats->total_orders,
                'counts' => $merchantStats->counts ?? [],
                'data_freshness' => optional($merchantStats->stats_computed_at)?->toIso8601String(),
            ] : null;
        }

        $this->cache->put($phoneNormalized, $payload, $accessTokenId);

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courierSnapshots(string $phoneNormalized): array
    {
        return CourierCustomerSnapshot::query()
            ->where('phone_normalized', $phoneNormalized)
            ->orderBy('courier')
            ->get()
            ->map(function (CourierCustomerSnapshot $snapshot) {
                $staticSample = RedxStaticSample::matchesSnapshot($snapshot);
                $raw = is_array($snapshot->raw_report) ? $snapshot->raw_report : [];

                $partial = max(0, (int) ($raw['partial'] ?? 0));

                return [
                    'courier' => $snapshot->courier,
                    'total_order' => $staticSample ? 0 : $snapshot->total_order,
                    'confirmed' => $staticSample ? 0 : $snapshot->confirmed,
                    'cancel' => $staticSample ? 0 : $snapshot->cancel,
                    'partial' => $staticSample ? 0 : $partial,
                    'success_rate' => $staticSample ? 'No order history found!' : $snapshot->success_rate,
                    'customer_rating' => $staticSample ? null : $snapshot->customer_rating,
                    'estimated_success_rate' => $staticSample ? null : ($raw['estimated_success_rate'] ?? null),
                    'data_type' => $staticSample ? 'delivery' : ($raw['data_type'] ?? null),
                    'delivery_ratio' => $staticSample ? null : ($raw['delivery_ratio'] ?? null),
                    'cancellation_ratio' => $staticSample ? null : ($raw['cancellation_ratio'] ?? null),
                    'counts_hidden' => $staticSample ? false : ! empty($raw['counts_hidden']),
                    'history_unknown' => $staticSample ? false : ! empty($raw['history_unknown']),
                    'api_success' => ! $staticSample && ! empty($raw['api_success']),
                    'frauds' => $staticSample ? [] : (is_array($raw['frauds'] ?? null) ? $raw['frauds'] : []),
                    'frauds_count' => $staticSample ? 0 : $snapshot->frauds_count,
                    'fetched_at' => optional($snapshot->fetched_at)?->toIso8601String(),
                    'fetch_failed' => $staticSample || $this->snapshotFetchFailed($snapshot),
                    'retryable' => ! $staticSample
                        && ($raw['retryable'] ?? true) !== false
                        && empty($raw['credential_error']),
                    'rate_limited' => ! empty($raw['rate_limited']),
                ];
            })
            ->values()
            ->all();
    }

    private function snapshotFetchFailed(CourierCustomerSnapshot $snapshot): bool
    {
        $raw = is_array($snapshot->raw_report) ? $snapshot->raw_report : [];

        if (! empty($raw['unavailable']) || ! empty($raw['credential_error']) || ! empty($raw['fetch_failed'])) {
            return true;
        }

        $message = strtolower((string) ($raw['message'] ?? ''));

        foreach (['session expired', 'credentials are invalid', 'login failed', 're-login failed', 'token expired'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
                return true;
            }
        }

        return ($raw['status'] ?? null) === 'unavailable'
            && (int) $snapshot->total_order === 0
            && ! filled($snapshot->customer_rating);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fraudNotes(string $phoneNormalized): array
    {
        return CourierFraudReport::query()
            ->where('phone_normalized', $phoneNormalized)
            ->orderByDesc('reported_at')
            ->limit(20)
            ->get()
            ->map(fn (CourierFraudReport $report) => [
                'courier' => $report->courier,
                'name' => $report->reporter_name,
                'details' => $report->details,
                'consignment_id' => $report->consignment_id,
                'created_at' => optional($report->reported_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
