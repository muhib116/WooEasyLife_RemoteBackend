<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\MerchantCustomerStats;
use App\Models\OrderIntelligence\PlatformCustomerStats;

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
            ->map(fn (CourierCustomerSnapshot $snapshot) => [
                'courier' => $snapshot->courier,
                'total_order' => $snapshot->total_order,
                'confirmed' => $snapshot->confirmed,
                'cancel' => $snapshot->cancel,
                'success_rate' => $snapshot->success_rate,
                'customer_rating' => $snapshot->customer_rating,
                'frauds_count' => $snapshot->frauds_count,
                'fetched_at' => optional($snapshot->fetched_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
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
