<?php

namespace App\Services\OrderIntelligence;

use App\Services\OrderIntelligence\Search\CustomerSearchManager;
use App\Services\OrderIntelligence\Search\DatabaseCustomerSearchDriver;

class IntelligenceSuggestService
{
    public function __construct(
        private CustomerSearchManager $searchManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function suggest(string $query, int $limit = 8): array
    {
        $startedAt = microtime(true);
        $query = preg_replace('/\D/', '', $query) ?? '';

        if (strlen($query) < (int) config('order_intelligence.suggest.min_query_length', 3)) {
            return [
                'query' => $query,
                'took_ms' => 0,
                'driver' => $this->driverName(),
                'suggestions' => [],
            ];
        }

        $maxLimit = (int) config('order_intelligence.suggest.max_limit', 20);
        $limit = max(1, min($limit, $maxLimit));

        return [
            'query' => $query,
            'took_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'driver' => $this->driverName(),
            'suggestions' => $this->searchManager->search($query, $limit),
        ];
    }

    private function driverName(): string
    {
        return $this->searchManager->driver() instanceof DatabaseCustomerSearchDriver
            ? 'database'
            : 'meilisearch';
    }
}
