<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseExperienceSignal;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseLanguageSurface;
use App\WiseAi\Training\TrainingPackImporter;
use App\WiseAi\Training\TrainingSchema;
use Illuminate\Support\Facades\Hash;

it('imports training json as knowledge drafts, language reviews, and experience signals', function () {
    $gen = WiseApiKey::generate('train-pack');
    $key = $gen['key'];
    $pack = TrainingSchema::examplePack();

    $stats = app(TrainingPackImporter::class)->import($key, $pack, true);

    expect($stats['knowledge_created'])->toBe(3)
        ->and($stats['knowledge_updated'])->toBe(0)
        ->and($stats['language_created'])->toBe(5)
        ->and($stats['language_updated'])->toBe(0)
        ->and($stats['language_reused'])->toBe(0)
        ->and($stats['experience_created'])->toBe(1)
        ->and($stats['experience_reused'])->toBe(0)
        ->and($stats['applied'])->toBeGreaterThan(0)
        ->and($stats['next_steps'])->not->toBeEmpty();

    $drafts = WiseKnowledgeItem::query()
        ->where('wise_api_key_id', $key->id)
        ->where('status', 'draft')
        ->count();
    expect($drafts)->toBe(3);

    $reviews = WiseLanguageReview::query()
        ->where('wise_api_key_id', $key->id)
        ->where('status', 'open')
        ->where('channel', 'train')
        ->get();
    expect($reviews)->toHaveCount(5);

    $plz = $reviews->firstWhere('token', 'plz');
    expect($plz)->not->toBeNull()
        ->and($plz->sample_text)->toBe('please')
        ->and($plz->suggested_category)->toBe('abbrev')
        ->and($plz->suggested_pack_slug)->toBe('core-bd');

    $tumar = $reviews->firstWhere('token', 'tumar');
    expect($tumar)->not->toBeNull()
        ->and($tumar->sample_text)->toBe('তোমার')
        ->and($tumar->suggested_category)->toBe('banglish');

    // Import alone never publishes language entries or BCLC surfaces.
    expect(WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->where('status', 'published')->count())->toBe(0);
    expect(WiseLanguageSurface::query()->where('evidence_source', 'import')->count())->toBe(0);

    expect(WiseExperienceSignal::query()->where('wise_api_key_id', $key->id)->count())->toBe(1);

    // Re-import upserts drafts / language reviews / reuses experience — no duplicates.
    $again = app(TrainingPackImporter::class)->import($key, $pack, true);
    expect($again['knowledge_created'])->toBe(0)
        ->and($again['knowledge_updated'])->toBe(3)
        ->and($again['language_created'])->toBe(0)
        ->and($again['language_updated'])->toBe(5)
        ->and($again['experience_created'])->toBe(0)
        ->and($again['experience_reused'])->toBe(1);

    expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('status', 'draft')->count())->toBe(3);
    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->where('status', 'open')->count())->toBe(5);
    expect(WiseExperienceSignal::query()->where('wise_api_key_id', $key->id)->count())->toBe(1);

    $key->delete();
});

it('skips language import when a published entry already exists for the surface', function () {
    $gen = WiseApiKey::generate('train-lang-reuse');
    $key = $gen['key'];

    WiseLanguageEntry::create([
        'wise_api_key_id' => $key->id,
        'type' => 'abbrev',
        'from_text' => 'plz',
        'to_text' => 'please',
        'status' => 'published',
        'enabled' => true,
        'version' => 1,
    ]);

    $stats = app(TrainingPackImporter::class)->import($key, [
        'version' => TrainingSchema::VERSION,
        'items' => [
            [
                'lane' => 'language',
                'category' => 'abbrev',
                'from' => 'plz',
                'to' => 'please',
            ],
            [
                'lane' => 'language',
                'category' => 'banglish',
                'from' => 'tumar',
                'to' => 'তোমার',
            ],
        ],
    ], false);

    expect($stats['language_reused'])->toBe(1)
        ->and($stats['language_created'])->toBe(1)
        ->and($stats['language_updated'])->toBe(0);

    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->where('token', 'plz')->count())->toBe(0);
    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->where('token', 'tumar')->where('channel', 'train')->count())->toBe(1);

    $key->delete();
});

it('rejects ambiguous and invalid language rows without creating reviews', function () {
    $gen = WiseApiKey::generate('train-lang-guard');
    $key = $gen['key'];

    $stats = app(TrainingPackImporter::class)->import($key, [
        'version' => TrainingSchema::VERSION,
        'items' => [
            ['lane' => 'language', 'category' => 'abbrev', 'from' => 'pp', 'to' => 'page'],
            ['lane' => 'language', 'category' => 'abbrev', 'from' => 'ok', 'to' => 'ok'],
            ['lane' => 'mystery', 'title' => 'x', 'answer' => 'y'],
            ['lane' => 'language', 'category' => 'abbrev', 'from' => 'PLZ', 'to' => 'Please'],
        ],
    ], false);

    expect($stats['language_created'])->toBe(1)
        ->and($stats['skipped'])->toBe(3)
        ->and($stats['errors'])->not->toBeEmpty();

    $plz = WiseLanguageReview::query()
        ->where('wise_api_key_id', $key->id)
        ->where('token', 'plz')
        ->where('channel', 'train')
        ->first();
    expect($plz)->not->toBeNull()
        ->and($plz->sample_text)->toBe('please');

    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->where('token', 'pp')->count())->toBe(0);

    $key->delete();
});

it('reuses language when a platform published entry already covers the surface', function () {
    $gen = WiseApiKey::generate('train-lang-platform');
    $key = $gen['key'];

    WiseLanguageEntry::create([
        'wise_api_key_id' => null,
        'type' => 'abbrev',
        'from_text' => 'tnx',
        'to_text' => 'thank you',
        'status' => 'published',
        'enabled' => true,
        'version' => 1,
    ]);

    $stats = app(TrainingPackImporter::class)->import($key, [
        'version' => TrainingSchema::VERSION,
        'items' => [
            ['lane' => 'language', 'category' => 'abbrev', 'from' => 'tnx', 'to' => 'thank you'],
        ],
    ], false);

    expect($stats['language_reused'])->toBe(1)
        ->and($stats['language_created'])->toBe(0);
    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->count())->toBe(0);

    $key->delete();
});

it('imports training pack via admin route as drafts only', function () {
    $admin = User::create([
        'name' => 'Wise Train Admin',
        'email' => 'wise-train-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $gen = WiseApiKey::generate('train-admin');
    $key = $gen['key'];

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'wise_api_key_id' => $key->id,
            'pack' => TrainingSchema::examplePack(),
            'import_experience' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('stats.knowledge_created', 3)
        ->assertJsonPath('stats.language_created', 5)
        ->assertJsonStructure(['next_steps', 'links' => ['knowledge', 'language']]);

    expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('status', 'published')->count())->toBe(0);
    expect(WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->where('status', 'published')->count())->toBe(0);

    $key->delete();
});

it('keeps merchant train scoped to the API key and rejects bad merchant targets', function () {
    $admin = User::create([
        'name' => 'Wise Merchant Train',
        'email' => 'wise-merchant-train-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $key = WiseApiKey::generate('merchant-train-ok')['key'];
    $inactive = WiseApiKey::generate('merchant-train-off')['key'];
    $inactive->update(['status' => 'disabled']);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'merchant',
            'pack' => TrainingSchema::examplePack(),
        ])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'merchant',
            'wise_api_key_id' => $inactive->id,
            'pack' => TrainingSchema::examplePack(),
        ])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'merchant',
            'wise_api_key_id' => $key->id,
            'pack' => TrainingSchema::examplePack(),
            'import_experience' => true,
        ])
        ->assertOk()
        ->assertJsonPath('stats.target', 'merchant')
        ->assertJsonPath('stats.knowledge_created', 3)
        ->assertJsonPath('stats.language_created', 5)
        ->assertJsonPath('stats.experience_created', 1);

    expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('status', 'draft')->count())->toBe(3)
        ->and(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('scope', 'platform')->count())->toBe(0)
        ->and(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->where('channel', 'train')->count())->toBe(5)
        ->and(WiseExperienceSignal::query()->where('wise_api_key_id', $key->id)->count())->toBe(1);

    // Merchant Train cannot sneak platform knowledge via JSON scope.
    $stats = app(TrainingPackImporter::class)->import($key, [
        'version' => TrainingSchema::VERSION,
        'items' => [[
            'lane' => 'knowledge',
            'type' => 'faq',
            'scope' => 'platform',
            'title' => 'Sneaky Platform',
            'question' => 'x?',
            'answer' => 'y',
        ]],
    ], false);
    expect($stats['knowledge_created'])->toBe(1);
    expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->where('title', 'Sneaky Platform')->value('scope'))
        ->toBe('merchant');

    $key->delete();
    $inactive->delete();
});

it('filters import pack by prompt_type and rejects merchant types on platform target', function () {
    $admin = User::create([
        'name' => 'Wise Train Filter',
        'email' => 'wise-train-filter-'.uniqid().'@example.com',
        'phone' => '019'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $key = WiseApiKey::generate('train-filter')['key'];

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'merchant',
            'wise_api_key_id' => $key->id,
            'prompt_type' => 'knowledge',
            'pack' => TrainingSchema::examplePack(),
            'import_experience' => true,
        ])
        ->assertOk()
        ->assertJsonPath('stats.knowledge_created', 3)
        ->assertJsonPath('stats.language_created', 0)
        ->assertJsonPath('stats.experience_created', 0)
        ->assertJsonPath('stats.lanes_dropped', 6);

    expect(WiseLanguageReview::query()->where('wise_api_key_id', $key->id)->count())->toBe(0);
    expect(WiseExperienceSignal::query()->where('wise_api_key_id', $key->id)->count())->toBe(0);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'platform',
            'prompt_type' => 'knowledge',
            'pack' => TrainingSchema::examplePlatformPack(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    $key->delete();
});

it('imports platform training without an API key as shared drafts and language reviews', function () {
    $pack = TrainingSchema::examplePlatformPack();

    $stats = app(TrainingPackImporter::class)->import(null, $pack, true);

    expect($stats['target'])->toBe('platform')
        ->and($stats['knowledge_created'])->toBe(2)
        ->and($stats['language_created'])->toBe(4)
        ->and($stats['experience_created'])->toBe(0)
        ->and($stats['applied'])->toBeGreaterThan(0);

    $drafts = WiseKnowledgeItem::query()
        ->whereNull('wise_api_key_id')
        ->where('scope', 'platform')
        ->where('status', 'draft')
        ->count();
    expect($drafts)->toBe(2);

    $reviews = WiseLanguageReview::query()
        ->whereNull('wise_api_key_id')
        ->where('channel', 'train')
        ->where('status', 'open')
        ->get();
    expect($reviews)->toHaveCount(4);
    expect($reviews->firstWhere('token', 'plz')?->sample_text)->toBe('please');

    // Experience rows in a mixed pack are skipped on platform.
    $withXp = app(TrainingPackImporter::class)->import(null, [
        'version' => TrainingSchema::VERSION,
        'items' => [
            ['lane' => 'language', 'category' => 'abbrev', 'from' => 'asap', 'to' => 'as soon as possible'],
            [
                'lane' => 'experience',
                'signal_type' => 'external',
                'intent' => 'price',
                'action' => 'clarify',
            ],
        ],
    ], true);
    expect($withXp['language_created'])->toBe(1)
        ->and($withXp['experience_created'])->toBe(0)
        ->and($withXp['skipped'])->toBeGreaterThanOrEqual(1);

    // Re-import upserts platform drafts / language reviews.
    $again = app(TrainingPackImporter::class)->import(null, $pack, true);
    expect($again['knowledge_created'])->toBe(0)
        ->and($again['knowledge_updated'])->toBe(2)
        ->and($again['language_created'])->toBe(0)
        ->and($again['language_updated'])->toBe(4);

    expect(WiseKnowledgeItem::query()->whereNull('wise_api_key_id')->where('status', 'published')->count())->toBe(0);
});

it('imports platform pack via admin route without wise_api_key_id', function () {
    $admin = User::create([
        'name' => 'Wise Platform Train',
        'email' => 'wise-platform-train-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'target' => 'platform',
            'pack' => TrainingSchema::examplePlatformPack(),
            'import_experience' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('stats.target', 'platform')
        ->assertJsonPath('stats.knowledge_created', 2)
        ->assertJsonPath('stats.language_created', 4);

    expect(WiseKnowledgeItem::query()->whereNull('wise_api_key_id')->where('status', 'draft')->count())->toBe(2);
    expect(WiseLanguageReview::query()->whereNull('wise_api_key_id')->where('channel', 'train')->count())->toBe(4);
});

it('returns 422 when every training item is invalid', function () {
    $admin = User::create([
        'name' => 'Wise Train Fail',
        'email' => 'wise-train-fail-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $gen = WiseApiKey::generate('train-fail');
    $key = $gen['key'];

    $this->actingAs($admin)
        ->postJson(route('wiseAi.train.import'), [
            'wise_api_key_id' => $key->id,
            'pack' => [
                'version' => TrainingSchema::VERSION,
                'items' => [
                    ['lane' => 'language', 'from' => 'pp', 'to' => 'page'],
                ],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('ok', false);

    $key->delete();
});
