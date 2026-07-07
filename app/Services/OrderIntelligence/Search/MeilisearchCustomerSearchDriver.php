<?php

namespace App\Services\OrderIntelligence\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeilisearchCustomerSearchDriver implements CustomerSearchDriver
{
    public function isAvailable(): bool
    {
        return filled(config('order_intelligence.search.meilisearch.host'));
    }

    public function upsert(array $document): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->ensureIndex();

        $response = $this->client()->post(
            '/indexes/' . $this->indexUid() . '/documents',
            [$document],
        );

        if (! $response->successful()) {
            Log::warning('Meilisearch customer index upsert failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $response = $this->client()->post('/indexes/' . $this->indexUid() . '/search', [
            'q' => $query,
            'limit' => $limit,
            'sort' => ['last_order_at:desc', 'total_orders:desc'],
        ]);

        if (! $response->successful()) {
            Log::warning('Meilisearch customer search failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        return collect($response->json('hits', []))
            ->map(fn (array $hit) => [
                'phone' => $hit['phone'] ?? '',
                'name' => $hit['name'] ?? null,
                'total_orders' => (int) ($hit['total_orders'] ?? 0),
                'risk_tier' => $hit['risk_tier'] ?? null,
                'delivery_rate' => $hit['delivery_rate'] ?? null,
                'label' => $hit['label'] ?? ($hit['phone'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function ensureIndex(): void
    {
        $client = $this->client();
        $indexUid = $this->indexUid();
        $response = $client->get('/indexes/' . $indexUid);

        if ($response->status() === 404) {
            $client->post('/indexes', [
                'uid' => $indexUid,
                'primaryKey' => 'id',
            ]);
        }

        $client->patch('/indexes/' . $indexUid . '/settings', [
            'searchableAttributes' => ['phone', 'name'],
            'sortableAttributes' => ['last_order_at', 'total_orders'],
        ]);
    }

    private function client()
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($key = config('order_intelligence.search.meilisearch.key')) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        return Http::baseUrl(rtrim((string) config('order_intelligence.search.meilisearch.host'), '/'))
            ->withHeaders($headers)
            ->timeout((int) config('order_intelligence.search.meilisearch.timeout', 5));
    }

    private function indexUid(): string
    {
        return (string) config('order_intelligence.search.meilisearch.index', 'platform_customers');
    }
}
