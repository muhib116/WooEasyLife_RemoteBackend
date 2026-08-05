<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseKnowledgeItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single + bulk publish for knowledge — human approval only.
 */
final class KnowledgePublisher
{
    public function publish(WiseKnowledgeItem $item): WiseKnowledgeItem
    {
        $item->update(['status' => 'published']);

        return $item->fresh(['apiKey:id,name']) ?? $item;
    }

    public function unpublish(WiseKnowledgeItem $item): WiseKnowledgeItem
    {
        $item->update(['status' => 'draft']);

        return $item->fresh(['apiKey:id,name']) ?? $item;
    }

    /**
     * Publish selected seeded drafts only. Non-eligible IDs are skipped (counted).
     *
     * @param  list<int>  $ids
     * @return array{published: list<WiseKnowledgeItem>, skipped: list<int>, published_count: int, skipped_count: int}
     */
    public function bulkPublishSeededDrafts(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));
        if ($ids === []) {
            throw new InvalidArgumentException('Select at least one seeded draft to publish.');
        }
        if (count($ids) > 200) {
            throw new InvalidArgumentException('Bulk publish is limited to 200 items per request.');
        }

        return DB::transaction(function () use ($ids) {
            $items = WiseKnowledgeItem::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $published = [];
            $skipped = [];

            foreach ($ids as $id) {
                $item = $items->get($id);
                if (! $item || ! SeededKnowledge::isBulkPublishEligible($item)) {
                    $skipped[] = $id;

                    continue;
                }
                $item->update(['status' => 'published']);
                $item->load('apiKey:id,name');
                $published[] = $item;
            }

            return [
                'published' => $published,
                'skipped' => $skipped,
                'published_count' => count($published),
                'skipped_count' => count($skipped),
            ];
        });
    }
}
