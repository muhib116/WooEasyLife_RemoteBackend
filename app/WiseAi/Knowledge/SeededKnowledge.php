<?php

namespace App\WiseAi\Knowledge;

use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\Seed\PlatformScriptCatalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Detect / filter platform + regional catalog seeds that need human publish.
 */
final class SeededKnowledge
{
    public const REGIONAL_SEEDER_KEY = 'regional_knowledge_seeder';

    /**
     * @return list<string>
     */
    public static function seederKeys(): array
    {
        return [
            PlatformScriptCatalog::SEEDER_KEY,
            self::REGIONAL_SEEDER_KEY,
        ];
    }

    public static function isSeeded(WiseKnowledgeItem $item): bool
    {
        $from = $item->meta['seeded_from'] ?? null;

        return is_string($from) && in_array($from, self::seederKeys(), true);
    }

    /**
     * Eligible for seeded bulk approve: owned seed draft, platform-shared (null key).
     */
    public static function isBulkPublishEligible(WiseKnowledgeItem $item): bool
    {
        return $item->status === 'draft'
            && $item->wise_api_key_id === null
            && self::isSeeded($item);
    }

    /**
     * True when an existing row was adopted away from this seeder (do not overwrite).
     */
    public static function isAdoptedAway(WiseKnowledgeItem $item, string $expectedSeederKey): bool
    {
        $from = $item->meta['seeded_from'] ?? null;

        return is_string($from) && $from !== '' && $from !== $expectedSeederKey;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function customerCopyChanged(WiseKnowledgeItem $item, array $payload): bool
    {
        return $item->answer !== ($payload['answer'] ?? null)
            || $item->question !== ($payload['question'] ?? null)
            || $item->title !== ($payload['title'] ?? null)
            || json_encode($item->keywords ?? []) !== json_encode($payload['keywords'] ?? []);
    }

    /**
     * Provenance identity drift (catalog version / sources) — still requires re-review.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function provenanceChanged(WiseKnowledgeItem $item, array $payload): bool
    {
        $nextMeta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
        $prevMeta = is_array($item->meta) ? $item->meta : [];

        if (array_key_exists('catalog_version', $nextMeta)
            && ($prevMeta['catalog_version'] ?? null) !== ($nextMeta['catalog_version'] ?? null)
        ) {
            return true;
        }

        if (array_key_exists('sources', $nextMeta)
            && json_encode($prevMeta['sources'] ?? null) !== json_encode($nextMeta['sources'] ?? null)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Keep human publish when customer-facing copy is unchanged.
     * Catalog version / sources meta bumps alone must not demote live seeds on bootstrap.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function shouldPreservePublished(WiseKnowledgeItem $item, array $payload): bool
    {
        if ($item->status !== 'published') {
            return false;
        }

        return ! self::customerCopyChanged($item, $payload);
    }

    /**
     * @param  Builder<WiseKnowledgeItem>  $query
     * @return Builder<WiseKnowledgeItem>
     */
    public static function scopeOwnedSeeds(Builder $query): Builder
    {
        return $query
            ->whereNull('wise_api_key_id')
            ->where(function (Builder $q): void {
                foreach (self::seederKeys() as $key) {
                    $q->orWhere('meta->seeded_from', $key);
                }
            });
    }

    /**
     * @param  Builder<WiseKnowledgeItem>  $query
     * @return Builder<WiseKnowledgeItem>
     */
    public static function scopeDraftsForReview(Builder $query): Builder
    {
        return self::scopeOwnedSeeds($query)->where('status', 'draft');
    }
}
