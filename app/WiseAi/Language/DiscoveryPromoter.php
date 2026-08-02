<?php

namespace App\WiseAi\Language;

use App\Models\WiseAi\WiseLanguageConcept;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseLanguageSurface;
use Illuminate\Support\Facades\DB;

/**
 * Human approve → SurfaceForm in a pack (+ optional merchant entry) → recompile.
 * Never auto-called from decide.
 */
class DiscoveryPromoter
{
    public function __construct(
        private PackCompiler $compiler,
        private RegionalPackProvisioner $regionalPacks,
    ) {}

    /**
     * @param  array{type: string, to_text?: string, scope?: string, pack_slug?: string, category?: string}  $input
     * @return array{entry: WiseLanguageEntry, surface_id: int|null, pack_slug: string|null, artifact_hash: string|null}
     */
    public function promote(WiseLanguageReview $review, array $input): array
    {
        $type = (string) $input['type'];
        $to = trim((string) ($input['to_text'] ?? ''));
        $scope = (string) ($input['scope'] ?? 'merchant');
        $from = mb_strtolower(trim((string) $review->token));
        if ($from === '' || in_array($from, PlatformLexicon::AMBIGUOUS, true)) {
            throw new \InvalidArgumentException('Ambiguous or empty surface cannot be promoted.');
        }
        if ($type !== 'filler' && $to === '') {
            throw new \InvalidArgumentException('to_text required unless type=filler.');
        }

        $packSlug = (string) ($input['pack_slug'] ?? $review->suggested_pack_slug ?: 'core-bd');
        $category = (string) ($input['category'] ?? $review->suggested_category ?: $type);

        return DB::transaction(function () use ($review, $type, $to, $scope, $from, $packSlug, $category) {
            $locked = WiseLanguageReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            // Platform Train reviews have no merchant key — always promote as platform.
            if ($locked->wise_api_key_id === null) {
                $scope = 'platform';
            }

            $keyId = $scope === 'platform' ? null : $locked->wise_api_key_id;
            if ($scope !== 'platform' && $keyId === null) {
                throw new \InvalidArgumentException(
                    'Merchant promote requires a key-scoped review — use Platform scope or import under an API key.'
                );
            }
            $entry = WiseLanguageEntry::query()
                ->where('type', $type)
                ->where('from_text', $from)
                ->when(
                    $keyId === null,
                    fn ($q) => $q->whereNull('wise_api_key_id'),
                    fn ($q) => $q->where('wise_api_key_id', $keyId),
                )
                ->lockForUpdate()
                ->first();

            if ($entry) {
                $entry->fill([
                    'to_text' => $type === 'filler' ? null : $to,
                    'status' => 'published',
                    'enabled' => true,
                    'version' => ((int) $entry->version) + 1,
                    'meta' => array_merge($entry->meta ?? [], [
                        'from_review_id' => $locked->id,
                        'promoted_at' => now()->toIso8601String(),
                        'pack_slug' => $packSlug,
                    ]),
                ]);
                $entry->save();
            } else {
                $entry = WiseLanguageEntry::create([
                    'wise_api_key_id' => $keyId,
                    'type' => $type,
                    'from_text' => $from,
                    'to_text' => $type === 'filler' ? null : $to,
                    'status' => 'published',
                    'enabled' => true,
                    'version' => 1,
                    'meta' => [
                        'from_review_id' => $locked->id,
                        'promoted_at' => now()->toIso8601String(),
                        'pack_slug' => $packSlug,
                    ],
                ]);
            }

            $surfaceId = null;
            $artifactHash = null;
            // Platform promotions always write BCLC surfaces. Regional packs also accept
            // merchant-scoped promote so Discovery can grow region-* without a second click.
            $writeSurface = $scope === 'platform' || str_starts_with($packSlug, 'region-');
            if ($writeSurface) {
                if (str_starts_with($packSlug, 'region-')) {
                    $pack = $this->regionalPacks->ensureFromPackSlug($packSlug);
                } else {
                    $pack = WiseLanguagePack::query()->where('slug', $packSlug)->first();
                }
                if (! $pack) {
                    throw new \InvalidArgumentException(
                        "BCLC pack “{$packSlug}” not found — bootstrap packs or pick a valid pack_slug."
                    );
                }

                $conceptKey = (string) ($locked->suggested_concept_key
                    ?: ($category.'.'.preg_replace('/[^a-z0-9_]+/u', '_', $from)));
                $concept = WiseLanguageConcept::query()->firstOrCreate(
                    ['pack_id' => $pack->id, 'concept_key' => $conceptKey],
                    [
                        'category' => $category,
                        'gloss_en' => $type === 'filler' ? 'filler strip' : $to,
                        'status' => 'published',
                    ]
                );
                if ($concept->category !== $category) {
                    $concept->category = $category;
                    $concept->status = 'published';
                    $concept->save();
                }

                $hash = hash('sha1', $from);
                $surface = WiseLanguageSurface::query()->updateOrCreate(
                    ['pack_id' => $pack->id, 'surface_hash' => $hash],
                    [
                        'concept_id' => $concept->id,
                        'surface_text' => $from,
                        'to_text' => $type === 'filler' ? '' : $to,
                        'script' => preg_match('/\p{Bengali}/u', $from) === 1 ? 'bengali' : 'latin',
                        'approval_status' => 'published',
                        'deprecated' => false,
                        'evidence_source' => 'review',
                        'meta' => [
                            'from_review_id' => $locked->id,
                            'promote_scope' => $scope,
                        ],
                    ]
                );
                $surfaceId = (int) $surface->id;
                $compiled = $this->compiler->compileAndPublish($pack->fresh());
                $artifactHash = $compiled['content_hash'];
                LanguageReviewIngestor::forgetKnownSurfaceCache();
            }

            $locked->update([
                'status' => 'promoted',
                'wise_language_entry_id' => $entry->id,
                'suggested_pack_slug' => $packSlug,
                'suggested_category' => $category,
                'handled_at' => now(),
            ]);

            LanguageNormalizer::forgetEntryCache($entry->wise_api_key_id);
            CorpusResolver::forgetCache();

            return [
                'entry' => $entry,
                'surface_id' => $surfaceId,
                'pack_slug' => $writeSurface ? $packSlug : null,
                'artifact_hash' => $artifactHash,
            ];
        });
    }
}
