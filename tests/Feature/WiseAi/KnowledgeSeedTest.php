<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\Knowledge\KnowledgeAnswerRegenerator;
use App\WiseAi\Knowledge\KnowledgePublisher;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
use App\WiseAi\Knowledge\Seed\PlatformKnowledgeSeeder;
use App\WiseAi\Knowledge\Seed\PlatformScriptCatalog;
use App\WiseAi\Knowledge\Seed\SeedQualityScorer;
use App\WiseAi\Language\RegionalKnowledgeSeeder;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

it('validates platform script catalog has no invented money or phones', function () {
    $validator = new KnowledgeSeedValidator;
    $errors = $validator->validateCatalog(PlatformScriptCatalog::items(), KnowledgeSchema::SCOPE_PLATFORM);
    expect($errors)->toBeEmpty();

    foreach (PlatformScriptCatalog::items() as $row) {
        expect($row['answer'])->not->toMatch('/\d+\s*(?:tk|taka|bdt|টাকা)/iu');
    }
});

it('rejects catalog rows that invent delivery fees', function () {
    $validator = new KnowledgeSeedValidator;
    $bad = [[
        'slug' => 'bad-fee',
        'type' => 'script',
        'title' => 'Bad',
        'question' => 'ডেলিভারি?',
        'answer' => 'ঢাকায় ডেলিভারি ৬০ টাকা।',
        'keywords' => ['delivery'],
    ]];
    $errors = $validator->validateCatalog($bad, KnowledgeSchema::SCOPE_PLATFORM);
    expect($errors)->not->toBeEmpty();
});

it('scores every seeded customer-facing script 10 out of 10 on the defined quality gate', function () {
    $scorer = app(SeedQualityScorer::class);

    $platform = $scorer->scoreCatalog(
        PlatformScriptCatalog::items(),
        KnowledgeSchema::SCOPE_PLATFORM,
    );
    $regional = $scorer->scoreCatalog(
        app(RegionalKnowledgeSeeder::class)->catalog(),
        KnowledgeSchema::SCOPE_REGION,
    );

    expect($platform['score'])->toBe(10.0)
        ->and($platform['perfect'])->toBeTrue()
        ->and($platform['failures'])->toBeEmpty()
        ->and($regional['score'])->toBe(10.0)
        ->and($regional['perfect'])->toBeTrue()
        ->and($regional['failures'])->toBeEmpty();
});

it('seeds platform knowledge scripts as drafts after validation', function () {
    $result = app(PlatformKnowledgeSeeder::class)->run();
    expect($result['upserted'])->toBe(count(PlatformScriptCatalog::items()));

    $n = WiseKnowledgeItem::query()
        ->whereNull('wise_api_key_id')
        ->where('scope', KnowledgeSchema::SCOPE_PLATFORM)
        ->where('external_id', 'like', 'wise-platform-%')
        ->where('status', 'draft')
        ->count();

    expect($n)->toBeGreaterThanOrEqual(count(PlatformScriptCatalog::items()));

    $delivery = WiseKnowledgeItem::query()
        ->where('external_id', 'wise-platform-delivery-ask-area')
        ->first();
    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('draft')
        ->and($delivery->meta['catalog_version'] ?? null)->toBe(PlatformScriptCatalog::VERSION)
        ->and($delivery->answer)->toContain('অনুমান');
});

it('only seeds platform scripts with a reachable business intent', function () {
    $engine = app(DecideEngine::class);

    foreach (PlatformScriptCatalog::items() as $row) {
        $classified = $engine->classify($row['question']);
        expect($classified['intent'])->toBeIn(DecideEngine::BUSINESS_INTENTS)
            ->and($classified['kind'])->toBe('business');
    }
});

it('classifies new payment, cod, and stock catalog questions to the mapped intents', function () {
    $engine = app(DecideEngine::class);
    $expected = [
        'payment-ask-method' => 'payment',
        'payment-policy-handoff' => 'payment',
        'payment-confirm-handoff' => 'payment',
        'cod-confirm-area' => 'cod',
        'cod-cash-on-clarify' => 'cod',
        'cod-handoff' => 'cod',
        'stock-ask-product' => 'stock',
        'stock-size-clarify' => 'stock',
        'stock-handoff' => 'stock',
    ];

    foreach ($expected as $slug => $intent) {
        $row = collect(PlatformScriptCatalog::items())->firstWhere('slug', $slug);
        expect($row)->not->toBeNull();
        expect($engine->classify($row['question'])['intent'])->toBe($intent);
    }
});

it('grounds a published payment platform seed on a matching decide turn', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $seed = WiseKnowledgeItem::query()
        ->where('external_id', 'wise-platform-payment-ask-method')
        ->firstOrFail();
    app(KnowledgePublisher::class)->bulkPublishSeededDrafts([$seed->id]);

    $key = WiseApiKey::generate('payment-platform-runtime')['key'];
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'bkash e payment kora jabe?',
        'channel' => 'test',
        'conversation_id' => 'payment-platform-runtime',
    ]));

    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($run['decision']['intent'] ?? null)->toBe('payment')
        ->and($run['decision']['suggested_reply'] ?? null)->toContain('মেথডে পেমেন্ট')
        ->and($run['decision']['suggested_reply'])->not->toMatch('/\d+\s*(?:tk|taka|bdt|টাকা)/iu');

    $key->delete();
});

it('artisan wise:seed-knowledge --validate-only passes', function () {
    $this->artisan('wise:seed-knowledge', ['--validate-only' => true])
        ->assertSuccessful();
});

it('regional seeder stays valid and expands order-status scripts as drafts', function () {
    $result = app(RegionalKnowledgeSeeder::class)->run();
    expect($result['upserted'])->toBeGreaterThanOrEqual(18); // 9 hubs × 2 reachable scripts

    $row = WiseKnowledgeItem::query()
        ->where('external_id', 'bclc-region-bogura-order-status')
        ->where('scope', KnowledgeSchema::SCOPE_REGION)
        ->first();
    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('draft')
        ->and($row->answer)->toContain('আন্দাজ');
});

it('retrieves a regional seed through the normal turn runner only when opted in and published', function () {
    app(RegionalKnowledgeSeeder::class)->run();
    $ids = WiseKnowledgeItem::query()
        ->where('external_id', 'like', 'bclc-region-mymensingh-%')
        ->pluck('id')
        ->all();
    app(KnowledgePublisher::class)->bulkPublishSeededDrafts($ids);

    $key = WiseApiKey::generate('regional-knowledge-runtime')['key'];

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'ডেলিভারি হবে?',
        'channel' => 'test',
        'conversation_id' => 'regional-knowledge-runtime',
        'context' => ['region' => 'kishorgonj'],
    ]));

    $evidence = $run['turn']->evidence ?? [];
    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($evidence['knowledge_scope'] ?? null)->toBe(KnowledgeSchema::SCOPE_REGION)
        ->and($evidence['title'] ?? null)->toContain('Mymensingh')
        ->and($run['decision']['suggested_reply'] ?? null)->toContain('ডেলিভারির জন্য');

    $key->delete();
});

it('grounds regional knowledge when merchant key meta opts in without context.region', function () {
    app(RegionalKnowledgeSeeder::class)->run();
    $ids = WiseKnowledgeItem::query()
        ->where('external_id', 'like', 'bclc-region-mymensingh-%')
        ->pluck('id')
        ->all();
    app(KnowledgePublisher::class)->bulkPublishSeededDrafts($ids);

    $key = WiseApiKey::generate('regional-key-meta')['key'];
    $meta = is_array($key->meta) ? $key->meta : [];
    $meta['language'] = array_merge(
        is_array($meta['language'] ?? null) ? $meta['language'] : [],
        ['region' => 'kishorgonj'],
    );
    $key->meta = $meta;
    $key->save();

    $run = app(TurnRunner::class)->run($key->fresh(), IncomingTurn::fromPayload([
        'text' => 'ডেলিভারি হবে?',
        'channel' => 'test',
        'conversation_id' => 'regional-key-meta-1',
        // No context.region — opt-in only via key meta.language.region
    ]));

    $evidence = $run['turn']->evidence ?? [];
    expect($run['decision']['source'] ?? null)->toBe('knowledge')
        ->and($evidence['knowledge_scope'] ?? null)->toBe(KnowledgeSchema::SCOPE_REGION)
        ->and($evidence['title'] ?? null)->toContain('Mymensingh');

    $key->delete();
});

it('prefers merchant evidence over a generic platform seed', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $ids = WiseKnowledgeItem::query()
        ->where('external_id', 'like', 'wise-platform-%')
        ->pluck('id')
        ->all();
    app(KnowledgePublisher::class)->bulkPublishSeededDrafts($ids);

    $key = WiseApiKey::generate('merchant-over-platform-seed')['key'];
    $merchant = WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'external_id' => 'merchant-delivery-evidence',
        'type' => KnowledgeSchema::KIND_POLICY,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'Merchant delivery policy',
        'question' => 'ডেলিভারি চার্জ কত?',
        'answer' => 'Merchant-approved delivery policy.',
        'keywords' => ['ডেলিভারি', 'চার্জ'],
        'status' => 'published',
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'ডেলিভারি চার্জ কত?',
        'channel' => 'test',
        'conversation_id' => 'merchant-over-platform-seed',
    ]));
    $evidence = $run['turn']->evidence ?? [];

    expect($evidence['knowledge_id'] ?? null)->toBe($merchant->id)
        ->and($run['decision']['suggested_reply'] ?? null)->toBe('Merchant-approved delivery policy.');

    $key->delete();
});

it('bulk publishes only seeded drafts and skips ineligible ids', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $seed = WiseKnowledgeItem::query()->where('external_id', 'wise-platform-delivery-ask-area')->firstOrFail();
    $merchantKey = WiseApiKey::generate('bulk-skip-merchant')['key'];
    $merchant = WiseKnowledgeItem::create([
        'wise_api_key_id' => $merchantKey->id,
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'Merchant only',
        'question' => 'q',
        'answer' => 'a',
        'keywords' => [],
        'status' => 'draft',
        'version' => 1,
    ]);

    $result = app(KnowledgePublisher::class)->bulkPublishSeededDrafts([$seed->id, $merchant->id, 999999]);

    expect($result['published_count'])->toBe(1)
        ->and($result['skipped_count'])->toBe(2)
        ->and($seed->fresh()->status)->toBe('published')
        ->and($merchant->fresh()->status)->toBe('draft');

    $merchantKey->delete();
});

it('regenerate answer proposal does not persist until apply', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $item = WiseKnowledgeItem::query()->where('external_id', 'wise-platform-delivery-ask-area')->firstOrFail();
    $original = $item->answer;

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'answer' => 'ডেলিভারির জন্য কোন এলাকা বলবেন? অনুমান করে চার্জ বলব না।',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    $config = app(\App\WiseAi\Language\LlmLanguageConfig::class);
    $config->update(['enabled' => true, 'api_key' => 'sk-test-wise-regen']);

    $proposal = app(KnowledgeAnswerRegenerator::class)->propose($item);

    expect($proposal['proposed_answer'])->toContain('এলাকা')
        ->and($item->fresh()->answer)->toBe($original)
        ->and($item->fresh()->status)->toBe('draft');

    $config->update(['api_key' => '__clear__']);
});

it('admin bulk publish route requires publisher and updates seeded drafts', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $ids = WiseKnowledgeItem::query()
        ->where('external_id', 'like', 'wise-platform-%')
        ->where('status', 'draft')
        ->limit(2)
        ->pluck('id')
        ->all();

    $admin = User::create([
        'name' => 'Wise Seed Publish Admin',
        'email' => 'wise-seed-publish-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.knowledge.bulkPublish'), ['ids' => $ids])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('published_count', count($ids));

    expect(
        WiseKnowledgeItem::query()->whereIn('id', $ids)->where('status', 'published')->count()
    )->toBe(count($ids));
});

it('re-seeds content or catalog version changes back to draft after human publish', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $item = WiseKnowledgeItem::query()->where('external_id', 'wise-platform-delivery-ask-area')->firstOrFail();
    app(KnowledgePublisher::class)->publish($item);
    expect($item->fresh()->status)->toBe('published');

    // Same catalog refresh keeps publish.
    app(PlatformKnowledgeSeeder::class)->run();
    expect($item->fresh()->status)->toBe('published');

    // Human edits answer → draft; re-seed with catalog copy still drafts until publish.
    $item->update(['answer' => 'মানুষের এডিট করা উত্তর — অনুমান করে বলব না।', 'status' => 'draft', 'version' => $item->version + 1]);
    app(PlatformKnowledgeSeeder::class)->run();
    $fresh = $item->fresh();
    expect($fresh->status)->toBe('draft')
        ->and($fresh->answer)->toContain('অনুমান'); // restored from catalog as draft
});

it('skips adopted regional rows that no longer belong to the regional seeder', function () {
    app(RegionalKnowledgeSeeder::class)->run();
    $row = WiseKnowledgeItem::query()
        ->where('external_id', 'bclc-region-bogura-order-status')
        ->firstOrFail();

    $meta = $row->meta ?? [];
    $meta['seeded_from'] = 'human_adopted';
    $row->update([
        'answer' => 'Human-owned regional answer — অনুমান করব না।',
        'meta' => $meta,
        'status' => 'published',
    ]);

    app(RegionalKnowledgeSeeder::class)->run();

    expect($row->fresh()->answer)->toBe('Human-owned regional answer — অনুমান করব না।')
        ->and($row->fresh()->meta['seeded_from'] ?? null)->toBe('human_adopted')
        ->and($row->fresh()->status)->toBe('published');
});

it('keeps published seed live when only catalog_version provenance changes', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $item = WiseKnowledgeItem::query()->where('external_id', 'wise-platform-delivery-ask-area')->firstOrFail();
    app(KnowledgePublisher::class)->publish($item);

    $meta = $item->fresh()->meta;
    $meta['catalog_version'] = '0.0.0-stale';
    $item->update(['meta' => $meta, 'status' => 'published']);

    app(PlatformKnowledgeSeeder::class)->run();

    expect($item->fresh()->status)->toBe('published')
        ->and($item->fresh()->meta['catalog_version'] ?? null)->toBe(PlatformScriptCatalog::VERSION);
});

it('re-drafts published seed when customer-facing copy changes', function () {
    app(PlatformKnowledgeSeeder::class)->run();
    $item = WiseKnowledgeItem::query()->where('external_id', 'wise-platform-delivery-ask-area')->firstOrFail();
    app(KnowledgePublisher::class)->publish($item);
    $item->update([
        'answer' => 'Old human-edited platform copy — still seeded_from catalog.',
        'status' => 'published',
    ]);

    app(PlatformKnowledgeSeeder::class)->run();

    expect($item->fresh()->status)->toBe('draft')
        ->and($item->fresh()->answer)->not->toBe('Old human-edited platform copy — still seeded_from catalog.');
});
