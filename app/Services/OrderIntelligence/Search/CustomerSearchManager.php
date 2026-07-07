<?php

namespace App\Services\OrderIntelligence\Search;

class CustomerSearchManager
{
    public function __construct(
        private DatabaseCustomerSearchDriver $databaseDriver,
        private MeilisearchCustomerSearchDriver $meilisearchDriver,
    ) {}

    public function driver(): CustomerSearchDriver
    {
        $driver = (string) config('order_intelligence.search.driver', 'database');

        if ($driver === 'meilisearch' && $this->meilisearchDriver->isAvailable()) {
            return $this->meilisearchDriver;
        }

        return $this->databaseDriver;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit): array
    {
        return $this->driver()->search($query, $limit);
    }
}
