<?php

namespace App\WiseAi\Knowledge\Seed;

use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\SeededKnowledge;

/**
 * Upsert validated platform scripts (clarify/handoff — never merchant fee facts).
 */
class PlatformKnowledgeSeeder
{
    public function __construct(
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
        private SeedQualityScorer $quality = new SeedQualityScorer,
    ) {}

    /**
     * @return array{upserted: int, version: string, sources: list<string>}
     */
    public function run(): array
    {
        $items = PlatformScriptCatalog::items();
        $this->validator->assertValid(
            $this->validator->validateCatalog($items, KnowledgeSchema::SCOPE_PLATFORM),
            'PlatformScriptCatalog',
        );
        $quality = $this->quality->scoreCatalog($items, KnowledgeSchema::SCOPE_PLATFORM);
        if (! $quality['perfect']) {
            throw new \InvalidArgumentException(
                'PlatformScriptCatalog copy quality must be 10.00/10 before seeding: '
                .json_encode($quality['failures'], JSON_UNESCAPED_UNICODE),
            );
        }

        $externalIds = array_map(
            static fn (array $row) => 'wise-platform-'.$row['slug'],
            $items,
        );
        // Remove only obsolete rows that this catalog itself owns; never touch human-published facts.
        WiseKnowledgeItem::query()
            ->whereNull('wise_api_key_id')
            ->where('scope', KnowledgeSchema::SCOPE_PLATFORM)
            ->where('meta->seeded_from', PlatformScriptCatalog::SEEDER_KEY)
            ->whereNotIn('external_id', $externalIds)
            ->delete();

        $upserted = 0;
        foreach ($items as $row) {
            $externalId = 'wise-platform-'.$row['slug'];
            $payload = [
                'wise_api_key_id' => null,
                'external_id' => $externalId,
                'type' => $row['type'],
                'scope' => KnowledgeSchema::SCOPE_PLATFORM,
                'title' => $row['title'],
                'question' => $row['question'],
                'answer' => $row['answer'],
                'keywords' => $row['keywords'],
                'meta' => array_merge([
                    'seeded_from' => PlatformScriptCatalog::SEEDER_KEY,
                    'catalog_version' => PlatformScriptCatalog::VERSION,
                    'sources' => PlatformScriptCatalog::sources(),
                    'situation' => $row['situation'] ?? null,
                ], $row['meta'] ?? []),
                // Always draft — humans publish via Knowledge bulk/single approve.
                'status' => 'draft',
                'version' => 1,
            ];

            $item = WiseKnowledgeItem::query()
                ->whereNull('wise_api_key_id')
                ->where('scope', KnowledgeSchema::SCOPE_PLATFORM)
                ->where('external_id', $externalId)
                ->first();

            if ($item) {
                if (SeededKnowledge::isAdoptedAway($item, PlatformScriptCatalog::SEEDER_KEY)) {
                    continue;
                }
                $changed = SeededKnowledge::customerCopyChanged($item, $payload)
                    || SeededKnowledge::provenanceChanged($item, $payload);
                $payload['version'] = $changed
                    ? max(1, (int) $item->version) + 1
                    : max(1, (int) $item->version);
                if (SeededKnowledge::shouldPreservePublished($item, $payload)) {
                    $payload['status'] = 'published';
                }
                $item->fill($payload);
                $item->save();
            } else {
                WiseKnowledgeItem::create($payload);
            }
            $upserted++;
        }

        return [
            'upserted' => $upserted,
            'version' => PlatformScriptCatalog::VERSION,
            'sources' => PlatformScriptCatalog::sources(),
        ];
    }
}
