<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseLanguageConcept;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguagePackAssignment;
use App\Models\WiseAi\WiseLanguageSurface;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Core BD + Commerce + Messenger + Regional packs, then compiles.
 * Regional packs are opt-in via region assignment (never platform-default).
 */
class BclcBootstrap
{
    public function __construct(
        private PlatformLexicon $lexicon,
        private PackCompiler $compiler,
    ) {}

    /**
     * @return array{packs: list<string>, artifacts: list<array{slug: string, hash: string, created: bool}>}
     */
    public function run(): array
    {
        return DB::transaction(function () {
            $lex = $this->lexicon->pack();
            $artifacts = [];
            $packSlugs = ['core-bd', 'commerce', 'messenger'];

            $core = $this->upsertPack('core-bd', 'core', 'Core Bangladesh', '1.0.0');
            $this->replacePackContent($core, [
                'abbrev' => $lex['abbrev'],
                'sms' => $lex['sms'],
                'banglish' => $lex['banglish'],
                'phonetic' => $lex['phonetic'],
                'filler' => array_fill_keys($lex['filler'], ''),
                'emoji' => $lex['emoji'],
            ]);
            $artifacts[] = $this->compileRow($core);

            $commerce = $this->upsertPack('commerce', 'commerce', 'Commerce Lexicon', '1.0.0');
            $this->replacePackContent($commerce, [
                'commerce' => $lex['commerce'],
            ]);
            $artifacts[] = $this->compileRow($commerce);

            $messenger = $this->upsertPack('messenger', 'channel', 'Messenger Channel', '1.0.0');
            $this->replacePackContent($messenger, [
                'messenger' => [
                    'inbox' => 'inbox',
                    'seen' => 'seen',
                    'reply asap' => 'reply as soon as possible',
                    'msg me' => 'message me',
                    'dm' => 'direct message',
                ],
                'abbrev' => [
                    'brb' => 'be right back',
                    'ttyl' => 'talk to you later',
                ],
            ]);
            $artifacts[] = $this->compileRow($messenger);

            // Platform default assignment: core → commerce → messenger (priority ascending).
            $this->assignPlatform($core, 10);
            $this->assignPlatform($commerce, 20);
            $this->assignPlatform($messenger, 30);

            // L3 regional — published + region-target only (merchant/context opt-in).
            foreach (RegionCode::seedCatalog() as $region => $def) {
                $slug = RegionCode::packSlug($region);
                $pack = $this->upsertPack($slug, 'region', $def['name'], '1.0.0', $region);
                $this->replacePackContent($pack, [
                    'banglish' => $def['banglish'],
                ]);
                $artifacts[] = $this->compileRow($pack);
                $this->assignRegion($pack, $region, 15);
                $packSlugs[] = $slug;
            }

            CorpusResolver::forgetCache();

            return [
                'packs' => $packSlugs,
                'artifacts' => $artifacts,
            ];
        });
    }

    private function upsertPack(
        string $slug,
        string $kind,
        string $name,
        string $semver,
        ?string $region = null,
    ): WiseLanguagePack {
        $pack = WiseLanguagePack::query()->firstOrNew(['slug' => $slug]);
        $meta = ['seeded_from' => PlatformLexicon::DICT_VERSION];
        if ($region) {
            $meta['region'] = $region;
        }
        $pack->fill([
            'kind' => $kind,
            'name' => $name,
            'semver' => $semver,
            'locale_scope' => $region ? 'bd-'.$region : 'bd',
            'depends_on' => $slug === 'core-bd' ? [] : ['core-bd'],
            'compiler_min_version' => 1,
            'meta' => $meta,
        ]);
        // Stay draft until compile publishes — never downgrade an already-published pack here.
        if (! $pack->exists || $pack->status === null || $pack->status === '') {
            $pack->status = 'draft';
        }
        $pack->save();

        return $pack;
    }

    /**
     * Upsert seed catalog into a pack without wiping human-promoted surfaces.
     *
     * @param  array<string, array<string, mixed>|array<int, string>>  $categories
     */
    private function replacePackContent(WiseLanguagePack $pack, array $categories): void
    {
        // Remove prior seed rows only — leave evidence_source=review (Discovery promote) intact.
        $seedSurfaceIds = WiseLanguageSurface::query()
            ->where('pack_id', $pack->id)
            ->where(function ($q) {
                $q->where('evidence_source', 'seed')
                    ->orWhereNull('evidence_source');
            })
            ->pluck('id');
        if ($seedSurfaceIds->isNotEmpty()) {
            WiseLanguageSurface::query()->whereIn('id', $seedSurfaceIds)->delete();
        }
        WiseLanguageConcept::query()
            ->where('pack_id', $pack->id)
            ->whereDoesntHave('surfaces')
            ->delete();

        foreach ($categories as $category => $entries) {
            if ($category === 'emoji') {
                foreach ($entries as $surface => $meta) {
                    $concept = WiseLanguageConcept::query()->firstOrCreate(
                        [
                            'pack_id' => $pack->id,
                            'concept_key' => 'emoji.'.md5((string) $surface),
                        ],
                        [
                            'category' => 'emoji',
                            'gloss_en' => is_array($meta) ? (($meta['signal'] ?? '').'/'.($meta['polarity'] ?? '')) : null,
                            'status' => 'published',
                            'meta' => is_array($meta) ? $meta : null,
                        ]
                    );
                    $this->createSurface($pack->id, $concept->id, (string) $surface, null, 'emoji', is_array($meta) ? $meta : null);
                }
                continue;
            }

            if ($category === 'filler') {
                foreach (array_keys($entries) as $surface) {
                    $from = mb_strtolower(trim((string) $surface));
                    if ($from === '') {
                        continue;
                    }
                    $concept = WiseLanguageConcept::query()->firstOrCreate(
                        [
                            'pack_id' => $pack->id,
                            'concept_key' => 'filler.'.$from,
                        ],
                        [
                            'category' => 'filler',
                            'gloss_en' => 'filler strip',
                            'status' => 'published',
                        ]
                    );
                    $this->createSurface($pack->id, $concept->id, $from, '', 'latin');
                }
                continue;
            }

            foreach ($entries as $from => $to) {
                $from = mb_strtolower(trim((string) $from));
                if ($from === '' || in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
                    continue;
                }
                $conceptKey = $category.'.'.preg_replace('/[^a-z0-9_]+/u', '_', $from);
                $concept = WiseLanguageConcept::query()->firstOrCreate(
                    [
                        'pack_id' => $pack->id,
                        'concept_key' => $conceptKey,
                    ],
                    [
                        'category' => $category,
                        'gloss_en' => (string) $to,
                        'gloss_bn' => preg_match('/\p{Bengali}/u', (string) $to) === 1 ? (string) $to : null,
                        'status' => 'published',
                    ]
                );
                $script = preg_match('/\p{Bengali}/u', $from) === 1 ? 'bengali' : 'latin';
                $this->createSurface($pack->id, $concept->id, $from, (string) $to, $script);
            }
        }
    }

    /** @return array{slug: string, hash: string, created: bool} */
    private function compileRow(WiseLanguagePack $pack): array
    {
        $result = $this->compiler->compileAndPublish($pack);

        return [
            'slug' => (string) $pack->slug,
            'hash' => $result['content_hash'],
            'created' => $result['created'],
        ];
    }

    private function assignPlatform(WiseLanguagePack $pack, int $priority): void
    {
        WiseLanguagePackAssignment::query()->updateOrCreate(
            [
                'target_type' => 'platform',
                'target_id' => 'default',
                'pack_id' => $pack->id,
            ],
            [
                'priority' => $priority,
                'enabled' => true,
                'meta' => ['role' => 'platform-default'],
            ]
        );
    }

    private function assignRegion(WiseLanguagePack $pack, string $region, int $priority): void
    {
        WiseLanguagePackAssignment::query()->updateOrCreate(
            [
                'target_type' => 'region',
                'target_id' => $region,
                'pack_id' => $pack->id,
            ],
            [
                'priority' => $priority,
                'enabled' => true,
                'meta' => ['role' => 'regional', 'region' => $region],
            ]
        );
    }

    /** @param  array<string, mixed>|null  $meta */
    private function createSurface(
        int $packId,
        int $conceptId,
        string $surfaceText,
        ?string $toText,
        string $script,
        ?array $meta = null,
    ): void {
        $hash = hash('sha1', $surfaceText);
        $existing = WiseLanguageSurface::query()
            ->where('pack_id', $packId)
            ->where('surface_hash', $hash)
            ->first();
        if ($existing) {
            // Never overwrite Discovery/human promotions with seed rows.
            if (($existing->evidence_source ?? '') === 'review') {
                return;
            }
            $existing->fill([
                'concept_id' => $conceptId,
                'to_text' => $toText,
                'script' => $script,
                'approval_status' => 'published',
                'deprecated' => false,
                'evidence_source' => 'seed',
                'meta' => $meta,
            ]);
            $existing->save();

            return;
        }

        WiseLanguageSurface::query()->create([
            'pack_id' => $packId,
            'concept_id' => $conceptId,
            'surface_text' => $surfaceText,
            'surface_hash' => $hash,
            'to_text' => $toText,
            'script' => $script,
            'approval_status' => 'published',
            'deprecated' => false,
            'evidence_source' => 'seed',
            'meta' => $meta,
        ]);
    }
}
