<?php

namespace App\Services\OrderIntelligence;

use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Services\FraudCheckService;

class AiIntelligenceService
{
    public function __construct(
        private FraudCheckService $fraudCheckService,
        private PlatformIntelligenceReader $platformIntelligenceReader,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function customerAiProfile(string $phone, ?int $accessTokenId = null): ?array
    {
        $phoneNormalized = $this->fraudCheckService->normalizePhone($phone);

        $stats = PlatformCustomerStats::query()
            ->with(['customer:id,phone_normalized,latest_name,first_seen_at,last_seen_at'])
            ->where('phone_normalized', $phoneNormalized)
            ->first();

        if (! $stats) {
            return null;
        }

        $platformData = $this->platformIntelligenceReader->forPhone($phoneNormalized, $accessTokenId);

        return [
            'phone' => $phoneNormalized,
            'name' => $stats->customer?->latest_name,
            'risk_score' => $stats->risk_score,
            'risk_tier' => $stats->risk_tier,
            'ai_profile' => $stats->ai_profile,
            'platform_intelligence' => $platformData['platform_intelligence'] ?? null,
            'your_store' => $platformData['your_store'] ?? null,
            'recommendation' => $this->recommendation($stats),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportFeatures(?int $accessTokenId = null, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min($perPage, 200));
        $page = max(1, $page);

        $query = PlatformCustomerStats::query()
            ->with(['customer:id,phone_normalized,latest_name'])
            ->orderByDesc('last_order_at');

        if ($accessTokenId !== null) {
            $query->whereIn('platform_customer_id', function ($sub) use ($accessTokenId) {
                $sub->select('platform_customer_id')
                    ->from('platform_orders')
                    ->where('access_token_id', $accessTokenId)
                    ->distinct();
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (PlatformCustomerStats $stats) => $this->featureRow($stats))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function featureRow(PlatformCustomerStats $stats): array
    {
        $profile = is_array($stats->ai_profile) ? $stats->ai_profile : [];

        return [
            'phone' => $stats->phone_normalized,
            'name' => $stats->customer?->latest_name,
            'risk_score' => $stats->risk_score,
            'risk_tier' => $stats->risk_tier,
            'total_orders' => $stats->total_orders,
            'total_merchants' => $stats->total_merchants,
            'delivery_rate' => $stats->delivery_rate,
            'return_rate' => $stats->return_rate,
            'features' => [
                'delivery_rate' => $profile['delivery_rate'] ?? null,
                'return_rate' => $profile['return_rate'] ?? null,
                'cancel_rate' => $profile['cancel_rate'] ?? null,
                'confirmation_rate' => $profile['confirmation_rate'] ?? null,
                'fraud_report_count' => $profile['fraud_report_count'] ?? 0,
                'avg_order_value' => $profile['avg_order_value'] ?? 0,
                'order_frequency_days' => $profile['order_frequency_days'] ?? null,
                'recency_days' => $profile['recency_days'] ?? null,
                'rfm' => $profile['rfm'] ?? null,
            ],
            'counts' => $stats->counts,
            'last_order_at' => optional($stats->last_order_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array{action: string, label: string, tone: string}
     */
    private function recommendation(PlatformCustomerStats $stats): array
    {
        return match ($stats->risk_tier) {
            'safe' => [
                'action' => 'approve',
                'label' => 'Safe to confirm order',
                'tone' => 'safe',
            ],
            'risky' => [
                'action' => 'review',
                'label' => 'High risk — verify before confirming',
                'tone' => 'risky',
            ],
            'caution' => [
                'action' => 'caution',
                'label' => 'Proceed with caution',
                'tone' => 'caution',
            ],
            default => [
                'action' => 'unknown',
                'label' => 'Insufficient platform history',
                'tone' => 'neutral',
            ],
        };
    }
}
