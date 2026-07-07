<?php

namespace App\Services\OrderIntelligence\Search;

use App\Models\OrderIntelligence\PlatformCustomerStats;

class DatabaseCustomerSearchDriver implements CustomerSearchDriver
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function upsert(array $document): void
    {
        // Database driver reads directly from platform_customer_stats.
    }

    public function search(string $query, int $limit): array
    {
        return PlatformCustomerStats::query()
            ->with(['customer:id,latest_name'])
            ->where('phone_normalized', 'like', $query . '%')
            ->orderByDesc('last_order_at')
            ->orderByDesc('total_orders')
            ->limit($limit)
            ->get()
            ->map(fn (PlatformCustomerStats $stats) => $this->formatSuggestion($stats))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSuggestion(PlatformCustomerStats $stats): array
    {
        $deliveryRate = is_array($stats->rates) ? ($stats->rates['delivery_rate'] ?? null) : null;
        $name = $stats->customer?->latest_name;

        return [
            'phone' => $stats->phone_normalized,
            'name' => $name,
            'total_orders' => $stats->total_orders,
            'risk_tier' => $stats->risk_tier,
            'delivery_rate' => $deliveryRate,
            'label' => trim(sprintf(
                '%s%s (%d orders)',
                $stats->phone_normalized,
                $name ? ' — ' . $name : '',
                $stats->total_orders,
            )),
        ];
    }
}
