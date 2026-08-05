<?php

namespace App\WiseAi\Knowledge\Search;

/**
 * Candidate ID prefilter for KnowledgeResolver. Never returns answer text for grounding.
 */
interface KnowledgeSearchDriver
{
    public function isAvailable(): bool;

    /**
     * @param  array{
     *     status?: string,
     *     types?: list<string>,
     *     wise_api_key_id?: int|null,
     *     exclude_platform?: bool
     * }  $filters
     * @return list<int>
     */
    public function search(string $query, array $filters, int $limit): array;

    /**
     * @param  array<string, mixed>  $document
     */
    public function upsert(array $document): void;

    public function delete(int $id): void;

    /** Optional full wipe before reindex. */
    public function clear(): void;
}
