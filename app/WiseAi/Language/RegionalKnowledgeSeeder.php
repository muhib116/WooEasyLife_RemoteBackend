<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\SeededKnowledge;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Knowledge\Seed\SeedQualityScorer;

/**
 * Region-scoped scripts (opt-in via context.region). Never invent fees/prices.
 */
class RegionalKnowledgeSeeder
{
    public function __construct(
        private KnowledgeSeedValidator $validator = new KnowledgeSeedValidator,
        private SeedQualityScorer $quality = new SeedQualityScorer,
    ) {}

    /**
     * @return array{upserted: int, regions: list<string>}
     */
    public function run(): array
    {
        $this->validator->assertValid(
            $this->validator->validateRegionalLexicon(),
            'RegionCode::seedCatalog banglish stems',
        );

        $catalog = $this->catalog();
        $this->validator->assertValid(
            $this->validator->validateCatalog($catalog, KnowledgeSchema::SCOPE_REGION),
            'RegionalKnowledgeSeeder catalog',
        );
        $quality = $this->quality->scoreCatalog($catalog, KnowledgeSchema::SCOPE_REGION);
        if (! $quality['perfect']) {
            throw new \InvalidArgumentException(
                'RegionalKnowledgeSeeder copy quality must be 10.00/10 before seeding: '
                .json_encode($quality['failures'], JSON_UNESCAPED_UNICODE),
            );
        }

        $externalIds = array_map(
            static fn (array $row) => 'bclc-region-'.$row['region'].'-'.$row['slug'],
            $catalog,
        );
        // Remove only obsolete rows owned by this regional catalog, not review/human knowledge.
        WiseKnowledgeItem::query()
            ->whereNull('wise_api_key_id')
            ->where('scope', KnowledgeSchema::SCOPE_REGION)
            ->where('meta->seeded_from', SeededKnowledge::REGIONAL_SEEDER_KEY)
            ->whereNotIn('external_id', $externalIds)
            ->delete();

        $upserted = 0;
        $regions = [];
        foreach ($catalog as $row) {
            $code = $row['region'];
            $regions[$code] = true;
            $externalId = 'bclc-region-'.$code.'-'.$row['slug'];
            $payload = [
                'wise_api_key_id' => null,
                'external_id' => $externalId,
                'type' => $row['type'],
                'scope' => KnowledgeSchema::SCOPE_REGION,
                'title' => $row['title'],
                'question' => $row['question'],
                'answer' => $row['answer'],
                'keywords' => $row['keywords'],
                'meta' => [
                    'region' => $code,
                    'seeded_from' => SeededKnowledge::REGIONAL_SEEDER_KEY,
                    'sources' => $row['sources'],
                    'places' => $row['places'],
                ],
                // Always draft for new rows — humans publish via Knowledge bulk/single approve.
                'status' => 'draft',
                'version' => 1,
            ];

            $item = WiseKnowledgeItem::query()
                ->whereNull('wise_api_key_id')
                ->where('scope', KnowledgeSchema::SCOPE_REGION)
                ->where('external_id', $externalId)
                ->first();

            if ($item) {
                if (SeededKnowledge::isAdoptedAway($item, SeededKnowledge::REGIONAL_SEEDER_KEY)) {
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

        return ['upserted' => $upserted, 'regions' => array_keys($regions)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $catalog = [];
        foreach (RegionCode::seedCatalog() as $code => $def) {
            $places = RegionCode::placeCoverage()[$code] ?? [ucfirst($code)];
            // Keep provenance specific to this hub; do not claim every global source supports it.
            $sources = array_values(array_unique($def['sources'] ?? []));

            foreach ($this->scriptsFor($code, $def['name']) as $row) {
                $catalog[] = array_merge($row, [
                    'region' => $code,
                    'places' => $places,
                    'sources' => $sources,
                ]);
            }
        }

        return $catalog;
    }

    /**
     * @return list<array{slug: string, type: string, title: string, question: string, answer: string, keywords: list<string>}>
     */
    private function scriptsFor(string $code, string $name): array
    {
        return [
            [
                'slug' => 'area-delivery',
                'type' => 'script',
                'title' => $name.' — ask delivery area',
                'question' => 'ডেলিভারি হবে?',
                'answer' => 'ডেলিভারির জন্য কোন জেলা বা উপজেলা লাগবে বলবেন? স্টোরের ডেলিভারি তথ্য থাকলে যাচাই করে জানানো যাবে; না থাকলে টিমকে জিজ্ঞেস করতে হবে।',
                'keywords' => ['delivery', 'ডেলিভারি', 'এলাকা', $code],
            ],
            [
                'slug' => 'order-status',
                'type' => 'script',
                'title' => $name.' — order status ask id',
                'question' => 'অর্ডারের খবর কী?',
                'answer' => 'অর্ডার নম্বর বা অর্ডারের ফোন নম্বর পাঠান। দেখে স্ট্যাটাস জানাই—আন্দাজ করে বলব না।',
                'keywords' => ['order', 'অর্ডার', 'track', $code],
            ],
        ];
    }
}
