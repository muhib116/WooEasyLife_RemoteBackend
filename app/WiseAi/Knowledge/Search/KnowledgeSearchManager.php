<?php

namespace App\WiseAi\Knowledge\Search;

use App\Models\WiseAi\WiseKnowledgeItem;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the configured knowledge search driver; fail-open to empty IDs (LIKE fallback).
 */
class KnowledgeSearchManager
{
    private ?KnowledgeSearchDriver $override = null;

    public function driver(): KnowledgeSearchDriver
    {
        if ($this->override !== null) {
            return $this->override;
        }

        return match ((string) config('wise_ai.knowledge_search.driver', 'database')) {
            'meilisearch' => app(MeilisearchKnowledgeSearchDriver::class),
            'inmemory' => app(InMemoryKnowledgeSearchDriver::class),
            default => app(DatabaseKnowledgeSearchDriver::class),
        };
    }

    public function useDriver(?KnowledgeSearchDriver $driver): void
    {
        $this->override = $driver;
    }

    /**
     * @param  array{
     *     status?: string,
     *     types?: list<string>,
     *     wise_api_key_id?: int|null,
     *     exclude_platform?: bool
     * }  $filters
     * @return list<int>
     */
    public function search(string $query, array $filters, int $limit): array
    {
        try {
            $driver = $this->driver();
            if (! $driver->isAvailable()) {
                return [];
            }

            return $driver->search($query, $filters, $limit);
        } catch (\Throwable $e) {
            Log::warning('Knowledge search manager failed open.', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function syncItem(WiseKnowledgeItem $item): void
    {
        try {
            $driver = $this->driver();
            if ($driver instanceof DatabaseKnowledgeSearchDriver) {
                return;
            }

            $doc = KnowledgeSearchDocument::fromItem($item);
            if ($doc === null) {
                $driver->delete((int) $item->id);

                return;
            }

            $driver->upsert($doc);
        } catch (\Throwable $e) {
            Log::warning('Knowledge search sync failed.', [
                'id' => $item->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function deleteItem(int $id): void
    {
        try {
            $driver = $this->driver();
            if ($driver instanceof DatabaseKnowledgeSearchDriver) {
                return;
            }
            $driver->delete($id);
        } catch (\Throwable $e) {
            Log::warning('Knowledge search delete failed.', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function reindexAll(): int
    {
        $driver = $this->driver();
        if ($driver instanceof DatabaseKnowledgeSearchDriver) {
            return 0;
        }

        $driver->clear();
        $count = 0;
        WiseKnowledgeItem::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($driver, &$count): void {
                foreach ($rows as $item) {
                    $doc = KnowledgeSearchDocument::fromItem($item);
                    if ($doc === null) {
                        continue;
                    }
                    $driver->upsert($doc);
                    $count++;
                }
            });

        return $count;
    }
}
