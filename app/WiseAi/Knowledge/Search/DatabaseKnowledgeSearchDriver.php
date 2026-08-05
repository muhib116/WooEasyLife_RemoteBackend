<?php

namespace App\WiseAi\Knowledge\Search;

/**
 * Default driver — resolver uses SQL LIKE; index ops are no-ops.
 */
class DatabaseKnowledgeSearchDriver implements KnowledgeSearchDriver
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function search(string $query, array $filters, int $limit): array
    {
        return [];
    }

    public function upsert(array $document): void {}

    public function delete(int $id): void {}

    public function clear(): void {}
}
