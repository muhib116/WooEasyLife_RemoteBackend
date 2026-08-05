<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseLanguageEntry;
use App\Models\WiseAi\WiseLanguageReview;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\Language\DiscoveryPromoter;
use App\WiseAi\Language\LanguageNormalizer;
use App\WiseAi\Language\MerchantLanguageEntryWriter;
use App\WiseAi\Playground\PlaygroundCoach;
use App\WiseAi\Playground\PlaygroundCoachApplier;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Hash;

function playgroundCoachAdmin(): User
{
    return User::create([
        'name' => 'Coach Admin',
        'email' => 'coach-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function playgroundCoachTurn(WiseApiKey $key, string $text, string $conversationId): WiseTurn
{
    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => $text,
        'channel' => 'test',
        'conversation_id' => $conversationId,
    ]));

    return $run['turn']->fresh();
}

it('normalizes illegal coach category and strips invented fee answers', function () {
    $key = WiseApiKey::generate('coach-norm')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-norm-1',
        'text' => 'delivery charge koto?',
        'payload' => ['text' => 'delivery charge koto?'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'delivery',
            'source' => 'gap_assist',
            'suggested_reply' => 'এলাকা বললে চার্জ জানাই।',
            'language' => ['canonical' => 'delivery charge কত?', 'ambiguous' => []],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => true,
    ]);

    $coach = app(PlaygroundCoach::class);
    $proposal = $coach->normalizeProposal([
        'category' => 'hack_me',
        'confidence' => 120,
        'rationale' => 'fee invent',
        'knowledge' => [
            'title' => 'Delivery',
            'question' => 'delivery charge koto?',
            'answer' => 'ঢাকায় ডেলিভারি ৬০ টাকা।',
            'keywords' => ['delivery'],
        ],
        'language' => [],
        'warnings' => [],
    ], $turn);

    expect($proposal['category'])->toBe(PlaygroundCoach::CATEGORY_KNOWLEDGE_FAQ)
        ->and($proposal['confidence'])->toBe(100)
        ->and($proposal['knowledge']['answer'])->not->toMatch('/\d+\s*(?:tk|taka|টাকা)/iu')
        ->and($proposal['warnings'])->not->toBeEmpty();

    $key->delete();
});

it('applies coach FAQ draft and publish from playground routes', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('coach-faq')['key'];
    $admin = playgroundCoachAdmin();

    try {
        $turn = playgroundCoachTurn($key, 'bkash e payment kora jabe?', 'coach-faq-1');
        expect($turn->gap)->toBeTrue();

        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'knowledge_faq',
                'publish_now' => false,
                'knowledge' => [
                    'title' => 'Bkash payment',
                    'question' => 'bkash e payment kora jabe?',
                    'answer' => 'কোন মেথডে পেমেন্ট করতে চান বলবেন? নিয়ম দেখে জানাই।',
                    'keywords' => ['payment'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', false)
            ->assertJsonPath('category', 'knowledge_faq');

        $turn->refresh();
        expect($turn->gap_handled_at)->not->toBeNull()
            ->and($turn->gap_knowledge_id)->not->toBeNull();

        $item = WiseKnowledgeItem::query()->findOrFail($turn->gap_knowledge_id);
        expect($item->status)->toBe('draft');
        $linkedId = (int) $item->id;
        $beforeCount = WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count();

        // Second apply on same handled gap reuses the linked FAQ — no orphan duplicate.
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'knowledge_faq',
                'publish_now' => true,
                'knowledge' => [
                    'title' => 'Bkash payment v2',
                    'question' => 'bkash e payment kora jabe?',
                    'answer' => 'কোন মেথডে পেমেন্ট করতে চান বলবেন? নিয়ম দেখে জানাই।',
                    'keywords' => ['payment'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('knowledge_item.id', $linkedId);

        expect(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count())->toBe($beforeCount)
            ->and(WiseKnowledgeItem::query()->findOrFail($linkedId)->title)->toBe('Bkash payment v2')
            ->and(WiseKnowledgeItem::query()->findOrFail($linkedId)->status)->toBe('published');

        // Fresh turn can still create/publish its own FAQ (may or may not be a gap).
        $turn2 = playgroundCoachTurn($key, 'nagad e payment kora jabe?', 'coach-faq-2');
        $resp2 = $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn2->id,
                'wise_api_key_id' => $key->id,
                'category' => 'knowledge_faq',
                'publish_now' => true,
                'knowledge' => [
                    'title' => 'Nagad payment',
                    'question' => 'nagad e payment kora jabe?',
                    'answer' => 'কোন মেথডে পেমেন্ট করতে চান বলবেন? নিয়ম দেখে জানাই।',
                    'keywords' => ['nagad', 'payment'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', true);

        $nagadId = (int) $resp2->json('knowledge_item.id');
        expect($nagadId)->toBeGreaterThan(0)
            ->and($nagadId)->not->toBe($linkedId)
            ->and(WiseKnowledgeItem::query()->findOrFail($nagadId)->status)->toBe('published')
            ->and(WiseKnowledgeItem::query()->findOrFail($nagadId)->title)->toBe('Nagad payment');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('blocks invented fees on coach FAQ apply', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('coach-fee')['key'];
    $admin = playgroundCoachAdmin();

    try {
        $turn = playgroundCoachTurn($key, 'delivery charge koto?', 'coach-fee-1');

        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'knowledge_faq',
                'publish_now' => true,
                'knowledge' => [
                    'title' => 'Delivery fee',
                    'question' => 'delivery charge koto?',
                    'answer' => 'ঢাকায় ডেলিভারি ৬০ টাকা।',
                    'keywords' => ['delivery'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('publishes merchant abbrev for pp and expands on normalize', function () {
    $key = WiseApiKey::generate('coach-pp')['key'];
    $admin = playgroundCoachAdmin();

    $before = app(LanguageNormalizer::class)->normalize('pp', $key);
    expect($before['canonical'])->toBe('pp')
        ->and($before['ambiguous'])->toContain('pp');

    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-pp-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'suggested_reply' => 'একটু পরিষ্কার করে বলবেন?',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    try {
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => true,
                'language' => [
                    'type' => 'abbrev',
                    'from' => 'pp',
                    'to' => 'দাম কত',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', true)
            ->assertJsonPath('language_entry.from', 'pp');

        $entry = WiseLanguageEntry::query()
            ->where('wise_api_key_id', $key->id)
            ->where('from_text', 'pp')
            ->first();
        expect($entry)->not->toBeNull()
            ->and($entry->status)->toBe('published')
            ->and($entry->meta['human_approved'] ?? null)->toBeTrue()
            ->and($entry->meta['source'] ?? null)->toBe(MerchantLanguageEntryWriter::META_SOURCE);

        LanguageNormalizer::forgetEntryCache($key->id);
        $after = app(LanguageNormalizer::class)->normalize('pp', $key);
        expect($after['canonical'])->toBe('দাম কত')
            ->and($after['ambiguous'])->not->toContain('pp');
    } finally {
        WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->delete();
        $key->delete();
    }
});

it('still rejects discovery promote of ambiguous pp', function () {
    $key = WiseApiKey::generate('coach-pp-block')['key'];
    $review = WiseLanguageReview::create([
        'wise_api_key_id' => $key->id,
        'token' => 'pp',
        'status' => 'open',
        'channel' => 'test',
        'sample_text' => 'pp',
        'suggested_pack_slug' => 'core-bd',
        'suggested_category' => 'abbrev',
    ]);

    try {
        expect(fn () => app(DiscoveryPromoter::class)->promote($review, [
            'type' => 'abbrev',
            'to_text' => 'দাম কত',
            'scope' => 'merchant',
        ]))->toThrow(InvalidArgumentException::class);
    } finally {
        $review->delete();
        $key->delete();
    }
});

it('requires publisher role for coach publish_now', function () {
    $key = WiseApiKey::generate('coach-perm')['key'];
    $admin = playgroundCoachAdmin();

    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-perm-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => ['intent' => 'unknown', 'source' => 'contract', 'language' => ['ambiguous' => ['pp']]],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    $this->mock(\App\WiseAi\Governance\WisePermission::class, function ($mock) {
        $mock->shouldReceive('canEdit')->andReturn(true);
        $mock->shouldReceive('canPublish')->andReturn(false);
    });

    try {
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => true,
                'language' => ['type' => 'abbrev', 'from' => 'pp', 'to' => 'দাম কত'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    } finally {
        WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->delete();
        $key->delete();
    }
});

it('draft language abbrev does not expand until published', function () {
    $key = WiseApiKey::generate('coach-pp-draft')['key'];
    $admin = playgroundCoachAdmin();

    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-pp-draft-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    try {
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => false,
                'language' => [
                    'type' => 'abbrev',
                    'from' => 'pp',
                    'to' => 'দাম কত',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('published', false);

        LanguageNormalizer::forgetEntryCache($key->id);
        $after = app(LanguageNormalizer::class)->normalize('pp', $key);
        expect($after['canonical'])->toBe('pp')
            ->and($after['ambiguous'])->toContain('pp');
    } finally {
        WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->delete();
        $key->delete();
    }
});

it('blocks invented fees in language_abbrev expansion', function () {
    $key = WiseApiKey::generate('coach-lang-fee')['key'];
    $admin = playgroundCoachAdmin();

    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-lang-fee-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    try {
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => true,
                'language' => [
                    'type' => 'abbrev',
                    'from' => 'pp',
                    'to' => 'ডেলিভারি ৬০ টাকা',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        expect(WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->count())->toBe(0);
    } finally {
        $key->delete();
    }
});

it('save draft does not unpublish an existing live language map', function () {
    $key = WiseApiKey::generate('coach-pp-keep')['key'];
    $admin = playgroundCoachAdmin();

    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-pp-keep-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    try {
        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => true,
                'language' => ['type' => 'abbrev', 'from' => 'pp', 'to' => 'দাম কত'],
            ])
            ->assertOk()
            ->assertJsonPath('published', true);

        $this->actingAs($admin)
            ->postJson(route('wiseAi.playground.coachApply'), [
                'turn_id' => $turn->id,
                'wise_api_key_id' => $key->id,
                'category' => 'language_abbrev',
                'publish_now' => false,
                'language' => ['type' => 'abbrev', 'from' => 'pp', 'to' => 'price koto'],
            ])
            ->assertOk()
            ->assertJsonPath('published', true);

        $entry = WiseLanguageEntry::query()
            ->where('wise_api_key_id', $key->id)
            ->where('from_text', 'pp')
            ->firstOrFail();
        expect($entry->status)->toBe('published')
            ->and($entry->to_text)->toBe('price koto');

        LanguageNormalizer::forgetEntryCache($key->id);
        $after = app(LanguageNormalizer::class)->normalize('pp', $key);
        expect($after['canonical'])->toBe('price koto');
    } finally {
        WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->delete();
        $key->delete();
    }
});

it('hints language_abbrev for ambiguous pp turns', function () {
    $key = WiseApiKey::generate('coach-hint')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-hint-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    expect(app(PlaygroundCoach::class)->hintCategory($turn))
        ->toBe(PlaygroundCoach::CATEGORY_LANGUAGE_ABBREV);

    $key->delete();
});

it('auto-detects knowledge_faq for product-effectiveness asks even if LLM says noop', function () {
    $key = WiseApiKey::generate('coach-auto-faq')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-auto-faq-1',
        'text' => 'এটা কি সত্যি কাজ করে?',
        'payload' => ['text' => 'এটা কি সত্যি কাজ করে?'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'action' => 'clarify',
            'source' => 'contract',
            'suggested_reply' => 'কোন প্রোডাক্টের কথা বলছেন?',
            'language' => ['canonical' => 'এটা কি সত্যি কাজ করে?', 'ambiguous' => []],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    $coach = app(PlaygroundCoach::class);
    expect($coach->hintCategory($turn))->toBe(PlaygroundCoach::CATEGORY_KNOWLEDGE_FAQ);

    $proposal = $coach->normalizeProposal([
        'category' => 'noop',
        'confidence' => 85,
        'rationale' => 'needs more context',
        'knowledge' => [],
        'language' => [],
        'warnings' => [],
    ], $turn);

    expect($proposal['category'])->toBe(PlaygroundCoach::CATEGORY_KNOWLEDGE_FAQ)
        ->and($proposal['warnings'])->not->toBeEmpty()
        ->and($proposal['knowledge']['question'])->toBe('এটা কি সত্যি কাজ করে?')
        ->and($proposal['knowledge']['answer'])->not->toBe('');

    $key->delete();
});

it('anchors FAQ question to customer utterance বিস্তারিত জানতে চাই', function () {
    $key = WiseApiKey::generate('coach-anchor')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-anchor-1',
        'text' => 'বিস্তারিত জানতে চাই',
        'payload' => ['text' => 'বিস্তারিত জানতে চাই'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'action' => 'clarify',
            'source' => 'contract',
            'suggested_reply' => 'দুঃখিত, একটু স্পষ্ট করে বলবেন?',
            'language' => ['canonical' => 'বিস্তারিত জানতে চাই', 'ambiguous' => []],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    $proposal = app(PlaygroundCoach::class)->normalizeProposal([
        'category' => 'knowledge_faq',
        'confidence' => 80,
        'rationale' => 'details ask',
        'knowledge' => [
            'title' => 'random llm title',
            'question' => 'কোন প্রোডাক্ট?',
            'answer' => '',
        ],
        'language' => [],
        'warnings' => [],
    ], $turn);

    expect($proposal['knowledge']['question'])->toBe('বিস্তারিত জানতে চাই')
        ->and($proposal['knowledge']['answer'])->toBe('দুঃখিত, একটু স্পষ্ট করে বলবেন?');

    $key->delete();
});

it('hits published FAQ for unknown-intent product-effectiveness ask', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('unknown-faq-hit')['key'];
    $q = 'এটা কি সত্যি কাজ করে?';
    $answer = 'আপনি কোন প্রোডাক্ট বা সার্ভিস সম্পর্কে জানতে চান?';

    try {
        $item = WiseKnowledgeItem::create([
            'wise_api_key_id' => $key->id,
            'type' => KnowledgeSchema::KIND_FAQ,
            'scope' => KnowledgeSchema::SCOPE_MERCHANT,
            'title' => $q,
            'question' => $q,
            'answer' => $answer,
            'keywords' => ['কাজ'],
            'status' => 'published',
            'version' => 1,
        ]);

        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => $q,
            'channel' => 'test',
            'conversation_id' => 'unknown-faq-hit-1',
        ]));

        expect($run['decision']['source'] ?? null)->toBe('knowledge')
            ->and($run['decision']['suggested_reply'] ?? null)->toBe($answer)
            ->and($run['decision']['gap'] ?? true)->toBeFalse()
            ->and($run['turn']->gap)->toBeFalse()
            ->and($run['turn']->trace['P3_ground']['result'] ?? null)->toBe('knowledge_hit')
            ->and($run['turn']->evidence['knowledge_id'] ?? null)->toBe($item->id)
            ->and($run['turn']->evidence['match_score'] ?? null)->toBeGreaterThan(0);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('soft-clarifies ambiguous pp even when support FAQ would substring-match', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('unknown-pp-guard')['key'];

    try {
        WiseKnowledgeItem::create([
            'wise_api_key_id' => $key->id,
            'type' => KnowledgeSchema::KIND_FAQ,
            'scope' => KnowledgeSchema::SCOPE_MERCHANT,
            'title' => 'support hours',
            'question' => 'what is support phone?',
            'answer' => 'Call support 9-5',
            'keywords' => ['support'],
            'status' => 'published',
            'version' => 1,
        ]);

        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'pp',
            'channel' => 'test',
            'conversation_id' => 'unknown-pp-guard-1',
        ]));

        expect($run['decision']['intent'] ?? null)->toBe('unknown')
            ->and($run['decision']['language']['ambiguous'] ?? [])->toContain('pp')
            ->and($run['decision']['source'] ?? null)->toBe('contract')
            ->and($run['decision']['action'] ?? null)->toBe('clarify')
            ->and($run['decision']['gap'] ?? true)->toBeFalse()
            ->and($run['turn']->gap)->toBeFalse()
            ->and($run['turn']->trace['P3_ground'] ?? null)->toBe('skip_unknown_ambiguous')
            ->and($run['decision']['suggested_reply'] ?? null)->not->toBe('Call support 9-5');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('keeps unknown miss as soft clarify not gap', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('unknown-miss-soft')['key'];

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'asdfqwerzxcv nonsense utterance xyz',
            'channel' => 'test',
            'conversation_id' => 'unknown-miss-soft-1',
        ]));

        expect($run['decision']['intent'] ?? null)->toBe('unknown')
            ->and($run['decision']['source'] ?? null)->toBe('contract')
            ->and($run['decision']['action'] ?? null)->toBe('clarify')
            ->and($run['decision']['gap'] ?? true)->toBeFalse()
            ->and($run['turn']->gap)->toBeFalse()
            ->and($run['turn']->trace['P3_ground'] ?? null)->toBe('skip_unknown_soft');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('auto-keeps language_abbrev when LLM wrongly picks FAQ for bare pp', function () {
    $key = WiseApiKey::generate('coach-auto-abbr')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-auto-abbr-1',
        'text' => 'pp',
        'payload' => ['text' => 'pp'],
        'config_snapshot' => [],
        'decision' => [
            'intent' => 'unknown',
            'source' => 'contract',
            'language' => ['canonical' => 'pp', 'ambiguous' => ['pp']],
        ],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    $proposal = app(PlaygroundCoach::class)->normalizeProposal([
        'category' => 'knowledge_faq',
        'confidence' => 70,
        'rationale' => 'price ask',
        'knowledge' => ['title' => 'pp', 'question' => 'pp', 'answer' => 'দাম জানতে প্রোডাক্ট বলুন।'],
        'language' => [],
        'warnings' => [],
    ], $turn);

    expect($proposal['category'])->toBe(PlaygroundCoach::CATEGORY_LANGUAGE_ABBREV);

    $key->delete();
});

it('applier noop returns without writing knowledge or language', function () {
    $key = WiseApiKey::generate('coach-noop')['key'];
    $turn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'coach-noop-1',
        'text' => 'hi',
        'payload' => ['text' => 'hi'],
        'config_snapshot' => [],
        'decision' => ['intent' => 'greeting', 'source' => 'contract'],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => false,
    ]);

    $beforeK = WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count();
    $beforeL = WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->count();

    $result = app(PlaygroundCoachApplier::class)->apply($turn, $key, [
        'category' => 'noop',
        'publish_now' => false,
    ]);

    expect($result['category'])->toBe('noop')
        ->and($result['knowledge_item'])->toBeNull()
        ->and($result['language_entry'])->toBeNull()
        ->and(WiseKnowledgeItem::query()->where('wise_api_key_id', $key->id)->count())->toBe($beforeK)
        ->and(WiseLanguageEntry::query()->where('wise_api_key_id', $key->id)->count())->toBe($beforeL);

    $key->delete();
});

it('blocks bare digits in a separate sentence even when refuse appears elsewhere', function () {
    $v = new \App\WiseAi\Knowledge\Seed\KnowledgeSeedValidator;
    expect($v->answerFactGuards('আন্দাজ করে বলব না। ঢাকায় ১২০।', 't'))->not->toBeEmpty()
        ->and($v->answerFactGuards('এলাকা বললে জানাই — আন্দাজ করে চার্জ বলব না।', 't'))->toBe([])
        ->and($v->answerFactGuards('আন্দাজ করে 60 বলব না।', 't'))->toBe([]);
});
