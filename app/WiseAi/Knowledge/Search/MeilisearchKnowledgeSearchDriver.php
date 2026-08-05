<?php

namespace App\WiseAi\Knowledge\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeilisearchKnowledgeSearchDriver implements KnowledgeSearchDriver
{
    public function isAvailable(): bool
    {
        return filled(config('wise_ai.knowledge_search.meilisearch.host'));
    }

    public function search(string $query, array $filters, int $limit): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        try {
            $this->ensureIndex();

            $payload = [
                'q' => $query,
                'limit' => max(1, $limit),
                'filter' => $this->buildFilter($filters),
            ];

            $response = $this->client()->post('/indexes/'.$this->indexUid().'/search', $payload);

            if (! $response->successful()) {
                Log::warning('Meilisearch knowledge search failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return collect($response->json('hits', []))
                ->map(fn (array $hit) => (int) ($hit['id'] ?? 0))
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('Meilisearch knowledge search exception.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function upsert(array $document): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        KnowledgeSearchDocument::assertNoSearchableAnswer($document);

        try {
            $this->ensureIndex();
            $response = $this->client()->post(
                '/indexes/'.$this->indexUid().'/documents',
                [$document],
            );
            if (! $response->successful()) {
                Log::warning('Meilisearch knowledge upsert failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Meilisearch knowledge upsert exception.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function delete(int $id): void
    {
        if (! $this->isAvailable() || $id <= 0) {
            return;
        }

        try {
            $response = $this->client()->delete('/indexes/'.$this->indexUid().'/documents/'.$id);
            if (! $response->successful() && $response->status() !== 404) {
                Log::warning('Meilisearch knowledge delete failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Meilisearch knowledge delete exception.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function clear(): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        try {
            $this->ensureIndex();
            $this->client()->delete('/indexes/'.$this->indexUid().'/documents');
        } catch (\Throwable $e) {
            Log::warning('Meilisearch knowledge clear exception.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>|string
     */
    private function buildFilter(array $filters): array|string
    {
        $parts = [];
        $status = (string) ($filters['status'] ?? 'published');
        $parts[] = 'status = "'.$this->escape($status).'"';

        $types = $filters['types'] ?? [];
        if (is_array($types) && $types !== []) {
            $quoted = array_map(fn ($t) => '"'.$this->escape((string) $t).'"', $types);
            $parts[] = 'type IN ['.implode(', ', $quoted).']';
        }

        $keyId = $filters['wise_api_key_id'] ?? null;
        $excludePlatform = (bool) ($filters['exclude_platform'] ?? false);

        if ($excludePlatform && $keyId !== null) {
            $parts[] = 'wise_api_key_id = '.(int) $keyId;
        } elseif ($keyId !== null) {
            $parts[] = '(wise_api_key_id = '.(int) $keyId.' OR wise_api_key_id IS NULL)';
        }

        return $parts;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function ensureIndex(): void
    {
        $client = $this->client();
        $indexUid = $this->indexUid();
        $response = $client->get('/indexes/'.$indexUid);

        if ($response->status() === 404) {
            $client->post('/indexes', [
                'uid' => $indexUid,
                'primaryKey' => 'id',
            ]);
        }

        $client->patch('/indexes/'.$indexUid.'/settings', [
            'searchableAttributes' => KnowledgeSearchDocument::SEARCHABLE,
            'filterableAttributes' => KnowledgeSearchDocument::FILTERABLE,
        ]);
    }

    private function client()
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($key = config('wise_ai.knowledge_search.meilisearch.key')) {
            $headers['Authorization'] = 'Bearer '.$key;
        }

        return Http::baseUrl(rtrim((string) config('wise_ai.knowledge_search.meilisearch.host'), '/'))
            ->withHeaders($headers)
            ->timeout((int) config('wise_ai.knowledge_search.meilisearch.timeout', 5));
    }

    private function indexUid(): string
    {
        return (string) config('wise_ai.knowledge_search.meilisearch.index', 'wise_knowledge_items');
    }
}
