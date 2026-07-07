<?php

namespace App\Services\OrderIntelligence\Search;

use App\Models\OrderIntelligence\PlatformCustomerStats;

class CustomerSearchIndexer
{
    public function __construct(
        private CustomerSearchManager $searchManager,
    ) {}

    public function indexCustomer(int $platformCustomerId): void
    {
        if (! config('order_intelligence.search.enabled', true)) {
            return;
        }

        $stats = PlatformCustomerStats::query()
            ->with(['customer:id,latest_name'])
            ->where('platform_customer_id', $platformCustomerId)
            ->first();

        if (! $stats) {
            return;
        }

        $document = $this->documentFromStats($stats);

        $this->searchManager->driver()->upsert($document);
    }

    public function reindexAll(int $chunkSize = 500): int
    {
        $indexed = 0;

        PlatformCustomerStats::query()
            ->with(['customer:id,latest_name'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$indexed) {
                foreach ($rows as $stats) {
                    $this->searchManager->driver()->upsert($this->documentFromStats($stats));
                    $indexed++;
                }
            });

        return $indexed;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentFromStats(PlatformCustomerStats $stats): array
    {
        $name = $stats->customer?->latest_name;
        $deliveryRate = is_array($stats->rates) ? ($stats->rates['delivery_rate'] ?? null) : null;

        return [
            'id' => (string) $stats->platform_customer_id,
            'phone' => $stats->phone_normalized,
            'name' => $name,
            'total_orders' => (int) $stats->total_orders,
            'risk_tier' => $stats->risk_tier,
            'delivery_rate' => $deliveryRate,
            'last_order_at' => optional($stats->last_order_at)?->timestamp ?? 0,
            'label' => trim(sprintf(
                '%s%s (%d orders)',
                $stats->phone_normalized,
                $name ? ' — ' . $name : '',
                $stats->total_orders,
            )),
        ];
    }
}
