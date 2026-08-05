<?php

use App\Models\User;
use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\Models\WiseAi\WiseTurn;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Intelligence\HealAlerts;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\Knowledge\RelatedQuestionSuggester;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\Learning\GapAutoDrafter;
use App\WiseAi\Learning\LearningInbox;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Hash;

it('auto-drafts merchant knowledge on gap without publishing or handling', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('heal-auto-draft')['key'];

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'delivery charge koto?',
            'channel' => 'test',
            'conversation_id' => 'heal-auto-1',
        ]));

        $turn = $run['turn']->fresh();
        expect($turn->gap)->toBeTrue()
            ->and($turn->gap_handled_at)->toBeNull()
            ->and($turn->gap_auto_draft_id)->not->toBeNull()
            ->and($run['decision']['heal']['auto_draft_id'] ?? null)->toBe($turn->gap_auto_draft_id);

        $draft = WiseKnowledgeItem::query()->findOrFail($turn->gap_auto_draft_id);
        expect($draft->status)->toBe('draft')
            ->and($draft->scope)->toBe(KnowledgeSchema::SCOPE_MERCHANT)
            ->and($draft->wise_api_key_id)->toBe($key->id)
            ->and($draft->meta['source'] ?? null)->toBe(GapAutoDrafter::META_SOURCE)
            ->and($draft->answer)->toBe($run['decision']['suggested_reply'])
            ->and($draft->answer)->not->toMatch('/\d+\s*(?:tk|taka|টাকা)/iu');

        $row = collect(app(LearningInbox::class)->feed('gap', 40))
            ->firstWhere('turn_id', $turn->id);
        expect($row)->not->toBeNull()
            ->and($row['auto_draft_id'])->toBe($turn->gap_auto_draft_id);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('human save from gap reuses auto-draft and can publish', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('heal-reuse-draft')['key'];
    $admin = User::create([
        'name' => 'Heal Admin',
        'email' => 'heal-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'bkash e payment kora jabe?',
            'channel' => 'test',
            'conversation_id' => 'heal-reuse-1',
        ]));
        $turn = $run['turn']->fresh();
        $autoId = $turn->gap_auto_draft_id;
        expect($autoId)->not->toBeNull();

        $this->actingAs($admin)
            ->postJson(route('wiseAi.gaps.draft', ['turn' => $turn->id]), [
                'type' => 'faq',
                'scope' => 'merchant',
                'title' => 'Bkash payment',
                'question' => 'bkash e payment kora jabe?',
                'answer' => 'কোন মেথডে পেমেন্ট করতে চান বলবেন? নিয়ম দেখে জানাই।',
                'publish_now' => true,
            ])
            ->assertOk()
            ->assertJsonPath('published', true);

        $turn->refresh();
        expect($turn->gap_handled_at)->not->toBeNull()
            ->and($turn->gap_knowledge_id)->toBe($autoId)
            ->and(WiseKnowledgeItem::query()->findOrFail($autoId)->status)->toBe('published')
            ->and(WiseKnowledgeItem::query()->findOrFail($autoId)->scope)->toBe(KnowledgeSchema::SCOPE_MERCHANT)
            ->and(WiseKnowledgeItem::query()->findOrFail($autoId)->wise_api_key_id)->toBe($key->id);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('blocks invented fees on gap draft publish and keeps auto-draft merchant when platform requested', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('heal-fee-guard')['key'];
    $admin = User::create([
        'name' => 'Heal Fee Guard',
        'email' => 'heal-fee-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'delivery charge koto?',
            'channel' => 'test',
            'conversation_id' => 'heal-fee-1',
        ]));
        $turn = $run['turn']->fresh();
        $autoId = $turn->gap_auto_draft_id;
        expect($autoId)->not->toBeNull();

        $this->actingAs($admin)
            ->postJson(route('wiseAi.gaps.draft', ['turn' => $turn->id]), [
                'type' => 'faq',
                'scope' => 'platform',
                'title' => 'Bad fee',
                'question' => 'delivery charge koto?',
                'answer' => 'ঢাকায় ডেলিভারি ৬০ টাকা।',
                'publish_now' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->actingAs($admin)
            ->postJson(route('wiseAi.gaps.draft', ['turn' => $turn->id]), [
                'type' => 'faq',
                'scope' => 'platform',
                'title' => 'Safe handoff',
                'question' => 'delivery charge koto?',
                'answer' => 'এলাকা বললে স্টোর পলিসি দেখে জানাই — আন্দাজ করে চার্জ বলব না।',
                'publish_now' => false,
            ])
            ->assertOk();

        $item = WiseKnowledgeItem::query()->findOrFail($autoId);
        expect($item->scope)->toBe(KnowledgeSchema::SCOPE_MERCHANT)
            ->and($item->wise_api_key_id)->toBe($key->id)
            ->and($item->status)->toBe('draft');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('suggests related published questions without returning answers', function () {
    $key = WiseApiKey::generate('heal-related')['key'];

    WiseKnowledgeItem::create([
        'wise_api_key_id' => $key->id,
        'type' => KnowledgeSchema::KIND_FAQ,
        'scope' => KnowledgeSchema::SCOPE_MERCHANT,
        'title' => 'Delivery charge FAQ',
        'question' => 'delivery charge koto outside dhaka?',
        'answer' => 'এলাকা বললে দেখে চার্জ জানাই; আন্দাজ করে বলব না।',
        'keywords' => ['delivery', 'charge'],
        'status' => 'published',
        'version' => 1,
    ]);

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'delivery charge koto?',
        'channel' => 'test',
        'conversation_id' => 'heal-related-1',
    ]));

    // If published matched, may not be a gap — force a gap turn for sibling path.
    $gapTurn = WiseTurn::create([
        'wise_api_key_id' => $key->id,
        'channel' => 'test',
        'conversation_id' => 'heal-related-gap',
        'text' => 'delivery charge koto for sylhet?',
        'payload' => ['text' => 'delivery charge koto for sylhet?'],
        'config_snapshot' => [],
        'decision' => ['intent' => 'delivery', 'action' => 'needs_human', 'gap' => true],
        'evidence' => null,
        'trace' => [],
        'status' => 'ok',
        'gap' => true,
    ]);

    $result = app(RelatedQuestionSuggester::class)->forTurn($gapTurn->load('apiKey'));
    expect($result['version'])->toBe(RelatedQuestionSuggester::VERSION)
        ->and($result['items'])->not->toBeEmpty();

    foreach ($result['items'] as $item) {
        expect($item)->toHaveKeys(['question', 'score', 'reason'])
            ->and($item)->not->toHaveKey('answer');
    }

    $admin = User::create([
        'name' => 'Related Admin',
        'email' => 'related-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('wiseAi.turns.relatedQuestions', ['turn' => $gapTurn->id]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonMissingPath('items.0.answer');

    unset($run);
    $key->delete();
});

it('emits heal alerts for high gap rate and low health', function () {
    $alerts = app(HealAlerts::class)->fromLive([
        'score' => 35,
        'label' => 'Critical',
        'metrics' => [
            'turns' => 20,
            'gap_rate' => 55.0,
        ],
    ], 30);

    expect(collect($alerts)->pluck('id')->all())
        ->toContain('high_gap_rate', 'low_ai_health', 'queue_gaps');
});
