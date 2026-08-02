<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguageArtifact;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguagePackAssignment;
use App\Models\WiseAi\WiseLanguageSurface;
use App\Models\WiseAi\WiseLanguageConcept;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Governance\GovernanceSealer;
use App\WiseAi\Language\BclcBootstrap;
use App\WiseAi\Language\CorpusResolver;
use App\WiseAi\Language\LanguageCorpus;
use App\WiseAi\Language\LanguageNormalizer;
use App\WiseAi\Language\LanguageReviewIngestor;
use App\WiseAi\Language\PackCompiler;
use App\WiseAi\TurnRunner;

it('bootstraps BCLC packs and publishes compiled artifacts', function () {
    $result = app(BclcBootstrap::class)->run();

    expect($result['packs'])->toContain('core-bd', 'commerce', 'messenger')
        ->and($result['packs'])->toContain('region-chattogram', 'region-sylhet', 'region-noakhali');

    foreach ($result['packs'] as $slug) {
        $pack = WiseLanguagePack::query()->where('slug', $slug)->first();
        expect($pack)->not->toBeNull();
        expect($pack->status)->toBe('published');

        $artifact = WiseLanguageArtifact::query()
            ->where('pack_id', $pack->id)
            ->where('status', 'published')
            ->first();
        expect($artifact)->not->toBeNull();
        expect($artifact->content_hash)->toHaveLength(64);
        expect($artifact->compiler_version)->toBe(LanguageCorpus::COMPILER_VERSION);

        $decoded = $artifact->decoded();
        $hash = app(PackCompiler::class)->contentHash($decoded);
        expect($hash)->toBe($artifact->content_hash);
        expect($hash)->not->toBe(hash('sha256', json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }

    // Regional packs must not be platform-default.
    expect(
        WiseLanguagePackAssignment::query()
            ->where('target_type', 'platform')
            ->whereIn('pack_id', WiseLanguagePack::query()->where('kind', 'region')->pluck('id'))
            ->exists()
    )->toBeFalse();
});

it('normalizes via compiled artifacts and seals language_corpus_snapshot', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $generated = WiseApiKey::generate('bclc-normalize');
    /** @var WiseApiKey $key */
    $key = $generated['key'];

    $language = app(LanguageNormalizer::class)->normalize('tnx vai cod?', $key, 'messenger');

    expect($language['canonical'])->toContain('thank you');
    expect($language['dict_version'])->toStartWith('bclc:');
    expect($language['corpus_snapshot']['packs'] ?? [])->not->toBeEmpty();
    expect($language['concepts_hit'] ?? [])->not->toBeEmpty();
    expect($language['corpus_snapshot']['overlays']['content_hash'] ?? null)->toBe('');

    $seal = app(GovernanceSealer::class)->seal(
        $key,
        (string) $language['dict_version'],
        $language['corpus_snapshot'],
    );
    expect($seal['language_corpus_snapshot']['packs'] ?? [])->not->toBeEmpty();
    expect($seal['language_corpus_snapshot']['sealed_at'] ?? null)->not->toBeNull();
    expect($seal['bclc_protocol_version'] ?? null)->toBe(LanguageCorpus::PROTOCOL_VERSION);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto',
        'channel' => 'test',
        'conversation_id' => 'bclc-1',
    ]));
    expect($run['decision']['language']['dict_version'] ?? '')->toStartWith('bclc:');
    $snapshot = $run['turn']->config_snapshot ?? [];
    expect($snapshot['language_corpus_snapshot']['packs'] ?? [])->not->toBeEmpty();
    expect($snapshot['language_corpus_snapshot']['sealed_at'] ?? null)->toBe($snapshot['sealed_at'] ?? null);

    $key->delete();
});

it('seals merchant language entries into corpus overlays for Replay', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();
    LanguageNormalizer::forgetEntryCache();

    $key = WiseApiKey::generate('bclc-overlay')['key'];
    WiseLanguageEntry::query()->create([
        'wise_api_key_id' => $key->id,
        'type' => 'abbrev',
        'from_text' => 'xyzzy',
        'to_text' => 'expanded-xyzzy',
        'status' => 'published',
        'enabled' => true,
        'version' => 3,
    ]);

    $language = app(LanguageNormalizer::class)->normalize('hello xyzzy', $key);
    expect($language['canonical'])->toContain('expanded-xyzzy');
    expect($language['dict_version'])->toContain('+ov:');
    expect($language['corpus_snapshot']['overlays']['entry_count'] ?? 0)->toBe(1);
    expect($language['corpus_snapshot']['overlays']['entries'][0]['version'] ?? null)->toBe(3);
    expect($language['corpus_snapshot']['overlays']['content_hash'] ?? '')->not->toBe('');

    $seal = app(GovernanceSealer::class)->seal($key, $language['dict_version'], $language['corpus_snapshot']);
    expect($seal['language_corpus_snapshot']['overlays']['entries'][0]['from'] ?? null)->toBe('xyzzy');

    $key->delete();
});

it('falls back when an assigned pack artifact is missing', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $pack = WiseLanguagePack::query()->where('slug', 'core-bd')->firstOrFail();
    WiseLanguageArtifact::query()->where('pack_id', $pack->id)->update(['status' => 'superseded']);
    CorpusResolver::forgetCache();

    $resolved = app(CorpusResolver::class)->resolve(null);
    expect($resolved['from_artifacts'])->toBeFalse();
    expect($resolved['corpus_snapshot']['fallback_reason'] ?? null)->toBe('incomplete_artifacts');
    expect($resolved['maps']['abbrev']['tnx'] ?? null)->toBe('thank you');
});

it('lets wise_api_key assignments win over platform packs regardless of lower numeric priority', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $key = WiseApiKey::generate('bclc-priority')['key'];
    $merchantPack = WiseLanguagePack::query()->create([
        'slug' => 'merchant-test-'.$key->id,
        'kind' => 'merchant',
        'name' => 'Merchant Test',
        'semver' => '1.0.0',
        'status' => 'draft',
        'locale_scope' => 'bd',
    ]);

    // Seed one surface that conflicts with platform abbrev tnx.
    $concept = $merchantPack->concepts()->create([
        'category' => 'abbrev',
        'concept_key' => 'abbrev.tnx',
        'gloss_en' => 'merchant thank you',
        'status' => 'published',
    ]);
    $merchantPack->surfaces()->create([
        'concept_id' => $concept->id,
        'surface_text' => 'tnx',
        'surface_hash' => hash('sha1', 'tnx'),
        'to_text' => 'merchant thank you',
        'script' => 'latin',
        'approval_status' => 'published',
        'deprecated' => false,
        'evidence_source' => 'seed',
    ]);
    app(PackCompiler::class)->compileAndPublish($merchantPack);

    // Priority 1 << platform messenger 30 — type rank must still win.
    WiseLanguagePackAssignment::query()->create([
        'target_type' => 'wise_api_key',
        'target_id' => (string) $key->id,
        'pack_id' => $merchantPack->id,
        'priority' => 1,
        'enabled' => true,
    ]);
    CorpusResolver::forgetCache();

    $language = app(LanguageNormalizer::class)->normalize('tnx', $key);
    expect($language['canonical'])->toBe('merchant thank you');

    $key->delete();
    $merchantPack->delete();
});

it('applies regional pack only when region is opted in', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $without = app(LanguageNormalizer::class)->normalize('aitta dam koto', null, null, null);
    expect($without['canonical'])->not->toContain('eta')
        ->and(collect($without['corpus_snapshot']['packs'] ?? [])->pluck('slug')->all())
        ->not->toContain('region-chattogram');

    $with = app(LanguageNormalizer::class)->normalize('aitta dam koto', null, null, 'chittagong');
    expect($with['canonical'])->toContain('eta')
        ->and(collect($with['corpus_snapshot']['packs'] ?? [])->pluck('slug')->all())
        ->toContain('region-chattogram')
        ->and($with['corpus_snapshot']['region'] ?? null)->toBe('chattogram');

    $key = WiseApiKey::generate('region-meta')['key'];
    $key->meta = array_merge($key->meta ?? [], ['language' => ['region' => 'sylhet']]);
    $key->save();

    $fromMeta = app(LanguageNormalizer::class)->normalize('oita kito', $key);
    expect($fromMeta['canonical'])->toContain('ota')
        ->and(collect($fromMeta['corpus_snapshot']['packs'] ?? [])->pluck('slug')->all())
        ->toContain('region-sylhet');

    $key->delete();
});

it('suggests regional pack for dialect surfaces', function () {
    $row = app(\App\WiseAi\Language\DiscoverySuggester::class)->suggest('aitta', 'aitta dam', 'chattogram');
    expect($row['pack_slug'])->toBe('region-chattogram');
});

it('soft-drops missing regional artifact without lexicon fallback', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $pack = WiseLanguagePack::query()->where('slug', 'region-chattogram')->firstOrFail();
    WiseLanguageArtifact::query()->where('pack_id', $pack->id)->update(['status' => 'superseded']);
    CorpusResolver::forgetCache();

    $resolved = app(CorpusResolver::class)->resolve(null, null, 'chattogram');
    expect($resolved['from_artifacts'])->toBeTrue()
        ->and($resolved['maps']['abbrev']['tnx'] ?? null)->toBe('thank you')
        ->and(collect($resolved['corpus_snapshot']['packs'] ?? [])->pluck('slug')->all())
        ->not->toContain('region-chattogram')
        ->and($resolved['corpus_snapshot']['soft_dropped_pack_ids'] ?? [])->not->toBeEmpty();
});

it('bootstrap reseed preserves review-promoted surfaces', function () {
    app(BclcBootstrap::class)->run();
    $pack = WiseLanguagePack::query()->where('slug', 'region-chattogram')->firstOrFail();
    $concept = $pack->concepts()->firstOrCreate(
        ['concept_key' => 'banglish.human_only_surface'],
        ['category' => 'banglish', 'gloss_en' => 'kept', 'status' => 'published']
    );
    \App\Models\WiseAi\WiseLanguageSurface::query()->updateOrCreate(
        ['pack_id' => $pack->id, 'surface_hash' => hash('sha1', 'human_only_surface')],
        [
            'concept_id' => $concept->id,
            'surface_text' => 'human_only_surface',
            'to_text' => 'kept by human',
            'script' => 'latin',
            'approval_status' => 'published',
            'deprecated' => false,
            'evidence_source' => 'review',
        ]
    );

    app(BclcBootstrap::class)->run();

    expect(
        \App\Models\WiseAi\WiseLanguageSurface::query()
            ->where('pack_id', $pack->id)
            ->where('surface_text', 'human_only_surface')
            ->where('evidence_source', 'review')
            ->exists()
    )->toBeTrue();
});

it('does not remap foreign dialect stems onto merchant region', function () {
    $suggester = app(\App\WiseAi\Language\DiscoverySuggester::class);
    // Chattogram stem while merchant region is sylhet → no regional suggest (falls through).
    $row = $suggester->suggest('aitta', 'aitta please', 'sylhet');
    expect($row['pack_slug'])->not->toBe('region-sylhet')
        ->and($row['pack_slug'])->not->toBe('region-chattogram');

    // Substring false positive: goita must not match oita.
    $false = $suggester->suggest('goita', 'goita ase', null);
    expect($false['pack_slug'])->not->toBe('region-sylhet');
});

it('grows opted-in regional pack for local banglish unknowns', function () {
    $suggester = app(\App\WiseAi\Language\DiscoverySuggester::class);
    $row = $suggester->suggest('zaibamure', 'zaibamure koto', 'barisal');
    expect($row['pack_slug'])->toBe('region-barisal');
});

it('provisions missing regional pack and promotes discovery surface into it', function () {
    $slug = 'region-barisal';
    $existing = WiseLanguagePack::query()->where('slug', $slug)->first();
    if ($existing) {
        WiseLanguageSurface::query()->where('pack_id', $existing->id)->delete();
        WiseLanguageConcept::query()->where('pack_id', $existing->id)->delete();
        WiseLanguageArtifact::query()->where('pack_id', $existing->id)->delete();
        WiseLanguagePackAssignment::query()->where('pack_id', $existing->id)->delete();
        $existing->delete();
    }

    $key = WiseApiKey::generate('region-grow')['key'];
    $review = \App\Models\WiseAi\WiseLanguageReview::create([
        'wise_api_key_id' => $key->id,
        'token' => 'zaibamure',
        'kind' => 'token',
        'status' => 'open',
        'hit_count' => 3,
        'suggested_pack_slug' => $slug,
        'suggested_category' => 'banglish',
        'sample_text' => 'zaibamure koto',
    ]);

    $result = app(\App\WiseAi\Language\DiscoveryPromoter::class)->promote($review, [
        'type' => 'banglish',
        'to_text' => 'jabo',
        'scope' => 'merchant',
        'pack_slug' => $slug,
        'category' => 'banglish',
    ]);

    expect($result['pack_slug'])->toBe($slug)
        ->and($result['surface_id'])->not->toBeNull()
        ->and($result['artifact_hash'])->not->toBeNull();

    $pack = WiseLanguagePack::query()->where('slug', $slug)->first();
    expect($pack)->not->toBeNull()
        ->and($pack->kind)->toBe('region');

    $surface = WiseLanguageSurface::query()
        ->where('pack_id', $pack->id)
        ->where('surface_text', 'zaibamure')
        ->first();
    expect($surface)->not->toBeNull()
        ->and($surface->evidence_source)->toBe('review')
        ->and($surface->to_text)->toBe('jabo');

    $with = app(LanguageNormalizer::class)->normalize(
        'zaibamure vai',
        $key,
        'messenger',
        'barisal',
    );
    expect($with['canonical'] ?? '')->toContain('jabo')
        ->and(collect($with['corpus_snapshot']['packs'] ?? [])->pluck('slug')->all())
        ->toContain('region-barisal');

    WiseLanguageSurface::query()->where('pack_id', $pack->id)->delete();
    WiseLanguageConcept::query()->where('pack_id', $pack->id)->delete();
    WiseLanguageArtifact::query()->where('pack_id', $pack->id)->delete();
    WiseLanguagePackAssignment::query()->where('pack_id', $pack->id)->delete();
    $pack->delete();
    $review->delete();
    WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->delete();
    $key->delete();
});

it('skips known regional phrases from discovery when region opted in', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();
    LanguageReviewIngestor::forgetKnownSurfaceCache();

    $key = WiseApiKey::generate('disc-region-known')['key'];
    app(LanguageReviewIngestor::class)->ingest($key, [
        'raw' => 'aitta dam koto xyzzyregionword',
        'unknown_tokens' => ['xyzzyregionword'],
        'canonical' => 'aitta dam koto xyzzyregionword',
    ], 1, 'test', 'chattogram');

    expect(\App\Models\WiseAi\WiseLanguageReview::query()->where('token', 'aitta dam')->exists())->toBeFalse();
    expect(\App\Models\WiseAi\WiseLanguageReview::query()->where('token', 'xyzzyregionword')->exists())->toBeTrue();

    $key->delete();
});
