<?php

namespace App\Services\OrderIntelligence\Search;

interface CustomerSearchDriver
{
    public function isAvailable(): bool;

    /**
     * @param  array<string, mixed>  $document
     */
    public function upsert(array $document): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit): array;
}
