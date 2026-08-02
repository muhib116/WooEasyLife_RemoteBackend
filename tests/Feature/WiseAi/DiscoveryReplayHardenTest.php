<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseLanguagePack;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseLanguageSurface;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Explain\ExplainBuilder;
use App\WiseAi\Language\BclcBootstrap;
use App\WiseAi\Language\CorpusResolver;
use App\WiseAi\Language\DiscoveryPromoter;
use App\WiseAi\Language\DiscoveryRanker;
use App\WiseAi\Language\LanguageReviewIngestor;
use App\WiseAi\Learning\LearningInbox;
use App\WiseAi\TurnRunner;

it('learning inbox exposes turn_id for gap and assist rows', function () {
    $key = WiseApiKey::generate('learn-turn-id')['key'];
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'gibberishxyzpriceplease',
        'channel' => 'test',
        'conversation_id' => 'learn-1',
    ]));

    // Force a gap-ish assist row: suggest_reply or clarify both ok for assist feed.
    $feed = app(LearningInbox::class)->feed('assist', 20);
    $row = collect($feed)->firstWhere('turn_id', $run['turn']->id);
    expect($row)->not->toBeNull()
        ->and($row['ref_id'])->toBe($run['turn']->id);

    $key->delete();
});

it('explain presents slim overlays without entry bodies', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();

    $key = WiseApiKey::generate('slim-ov')['key'];
    \App\Models\WiseAi\WiseLanguageEntry::query()->create([
        'wise_api_key_id' => $key->id,
        'type' => 'abbrev',
        'from_text' => 'zztest',
        'to_text' => 'expanded',
        'status' => 'published',
        'enabled' => true,
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'hello zztest',
        'channel' => 'test',
    ]));

    $snapshot = $run['turn']->config_snapshot['language_corpus_snapshot']['overlays'] ?? [];
    expect($snapshot['entries'] ?? [])->not->toBeEmpty();

    $explain = app(ExplainBuilder::class)->build($run['turn']);
    $presented = $explain['sealed']['language_corpus_snapshot']['overlays'] ?? [];
    expect($presented['content_hash'] ?? '')->not->toBe('')
        ->and($presented)->not->toHaveKey('entries')
        ->and($presented['entry_ids'] ?? [])->not->toBeEmpty();

    $key->delete();
});

it('discovery skips known lexicon phrases and queues rank off hot path', function () {
    app(BclcBootstrap::class)->run();
    CorpusResolver::forgetCache();
    LanguageReviewIngestor::forgetKnownSurfaceCache();

    $key = WiseApiKey::generate('disc-l2')['key'];
    $language = [
        'raw' => 'vai dam koto xyzzyword',
        'unknown_tokens' => ['xyzzyword'],
        'canonical' => 'vai dam koto xyzzyword',
    ];
    app(LanguageReviewIngestor::class)->ingest($key, $language, 99, 'messenger');

    $review = WiseLanguageReview::query()->where('token', 'xyzzyword')->first();
    expect($review)->not->toBeNull()
        ->and($review->suggested_pack_slug)->not->toBeNull()
        ->and((float) $review->rank_score)->toBeGreaterThan(0)
        ->and((int) $review->key_breadth)->toBe(1);

    // Known banglish ("dam koto") and phrases containing it must not enter Discovery.
    expect(WiseLanguageReview::query()->where('token', 'dam koto')->exists())->toBeFalse();
    expect(WiseLanguageReview::query()->where('token', 'vai dam koto')->exists())->toBeFalse();

    // Cross-key rank refresh is deferred until flush (not sync on ingest).
    $flushed = app(DiscoveryRanker::class)->flushQueued();
    expect($flushed)->toBeGreaterThan(0);
    expect((int) $review->fresh()->key_breadth)->toBe(1);

    $result = app(DiscoveryPromoter::class)->promote($review, [
        'type' => 'banglish',
        'to_text' => 'expanded-xyzzy',
        'scope' => 'platform',
        'pack_slug' => 'core-bd',
        'category' => 'banglish',
    ]);

    expect($result['surface_id'])->not->toBeNull()
        ->and($result['artifact_hash'])->not->toBeNull();
    expect(
        WiseLanguageSurface::query()->where('surface_text', 'xyzzyword')->where('approval_status', 'published')->exists()
    )->toBeTrue();
    expect(WiseLanguagePack::query()->where('slug', 'core-bd')->value('status'))->toBe('published');

    $key->delete();
});

it('platform promote fails when BCLC pack slug is missing', function () {
    $key = WiseApiKey::generate('disc-miss-pack')['key'];
    $review = WiseLanguageReview::query()->create([
        'wise_api_key_id' => $key->id,
        'token' => 'uniqmisspacktoken',
        'kind' => 'token',
        'hit_count' => 1,
        'status' => 'open',
        'suggested_pack_slug' => 'core-bd',
        'suggested_category' => 'banglish',
        'rank_score' => 1,
        'key_breadth' => 1,
        'last_seen_at' => now(),
    ]);

    expect(fn () => app(DiscoveryPromoter::class)->promote($review, [
        'type' => 'banglish',
        'to_text' => 'x',
        'scope' => 'platform',
        'pack_slug' => 'pack-does-not-exist',
        'category' => 'banglish',
    ]))->toThrow(\InvalidArgumentException::class);

    expect($review->fresh()->status)->toBe('open')
        ->and($review->fresh()->wise_language_entry_id)->toBeNull();

    $key->delete();
});

it('discovery key_breadth ignores sandbox keys', function () {
    $prod = WiseApiKey::generate('disc-prod')['key'];
    $sandbox = WiseApiKey::generate('disc-sand')['key'];
    $sandbox->meta = array_merge($sandbox->meta ?? [], ['sandbox' => true, 'governance' => ['sandbox' => true]]);
    $sandbox->save();

    $lang = [
        'raw' => 'qwertyuniqphrase delivery hobe later',
        'unknown_tokens' => ['qwertyuniqphrase'],
        'canonical' => 'qwertyuniqphrase',
    ];
    LanguageReviewIngestor::forgetKnownSurfaceCache();
    app(LanguageReviewIngestor::class)->ingest($prod, $lang, 1, 'test');
    app(LanguageReviewIngestor::class)->ingest($sandbox, $lang, 2, 'test');

    app(DiscoveryRanker::class)->flushQueued();

    $prodRow = WiseLanguageReview::query()
        ->where('wise_api_key_id', $prod->id)
        ->where('token', 'qwertyuniqphrase')
        ->first();
    expect($prodRow)->not->toBeNull()
        ->and((int) $prodRow->key_breadth)->toBe(1);

    $prod->delete();
    $sandbox->delete();
});

it('learning language feed orders by rank_score', function () {
    $key = WiseApiKey::generate('learn-rank')['key'];
    WiseLanguageReview::query()->create([
        'wise_api_key_id' => $key->id,
        'token' => 'lowrankzzz',
        'kind' => 'token',
        'hit_count' => 99,
        'status' => 'open',
        'rank_score' => 1.0,
        'key_breadth' => 1,
        'last_seen_at' => now(),
    ]);
    WiseLanguageReview::query()->create([
        'wise_api_key_id' => $key->id,
        'token' => 'highrankzzz',
        'kind' => 'token',
        'hit_count' => 2,
        'status' => 'open',
        'rank_score' => 50.0,
        'key_breadth' => 3,
        'last_seen_at' => now()->subMinute(),
    ]);

    $titles = collect(app(LearningInbox::class)->feed('language', 50))->pluck('title')->values();
    $idxHigh = $titles->search('highrankzzz');
    $idxLow = $titles->search('lowrankzzz');
    expect($idxHigh)->not->toBeFalse()
        ->and($idxLow)->not->toBeFalse()
        ->and($idxHigh)->toBeLessThan($idxLow);

    $key->delete();
});
