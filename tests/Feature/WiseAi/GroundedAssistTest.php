<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Assist\ContradictionDetector;
use App\WiseAi\Assist\GroundedAssistEngine;
use App\WiseAi\Assist\GroundedAssistSchema;
use App\WiseAi\Assist\ToolDecision;
use App\WiseAi\Assist\TrainingPackComposer;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\Context\ContextPackBuilder;
use App\WiseAi\Knowledge\KnowledgeSchema;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\Language\LlmLanguageConfig;
use App\WiseAi\TurnRunner;
use Illuminate\Support\Facades\Http;

it('normalizes grounded assist candidate and strips foreign knowledge ids', function () {
    $normalized = GroundedAssistSchema::normalizeCandidate([
        'reply' => 'ডেলিভারি চার্জ আন্দাজ করে বলব না — এলাকা বলুন।',
        'reasoning' => 'no fee in evidence',
        'plan' => ['clarify area', 'then answer'],
        'need_clarify' => true,
        'confidence' => 96,
        'score' => 9.2,
        'used_knowledge_ids' => [10, 99, 'x'],
        'intent_refined' => 'delivery',
    ], [10]);

    expect($normalized['used_knowledge_ids'])->toBe([10])
        ->and($normalized['need_clarify'])->toBeTrue()
        ->and($normalized['score'])->toBe(9.2)
        ->and($normalized['confidence'])->toBe(96);
});

it('rejects assist replies that invent fee digits not in evidence', function () {
    $engine = app(GroundedAssistEngine::class);
    $accepted = $engine->acceptCandidate([
        'reply' => 'ডেলিভারি চার্জ ৬০ টাকা',
        'score' => 9.5,
        'confidence' => 99,
        'need_clarify' => false,
        'used_knowledge_ids' => [],
        'plan' => ['answer'],
        'reasoning' => 'guess',
    ], [
        'evidence_pack' => [
            ['id' => 1, 'title' => 'delivery', 'answer' => 'এলাকা বললে চার্জ বলব'],
        ],
        'tool_facts' => [],
    ]);

    expect($accepted)->toBeNull();
});

it('accepts assist replies grounded on evidence digits', function () {
    $engine = app(GroundedAssistEngine::class);
    $accepted = $engine->acceptCandidate([
        'reply' => 'ঢাকায় ডেলিভারি ৬০ টাকা',
        'score' => 9.5,
        'confidence' => 99,
        'need_clarify' => false,
        'used_knowledge_ids' => [5],
        'plan' => ['answer'],
        'reasoning' => 'from faq',
    ], [
        'evidence_pack' => [
            ['id' => 5, 'title' => 'dhaka delivery', 'answer' => 'ঢাকায় ডেলিভারি ৬০ টাকা'],
        ],
        'tool_facts' => [],
    ]);

    expect($accepted)->not->toBeNull()
        ->and($accepted['reply'])->toContain('৬০');
});

it('builds context pack with thread funnel and tool facts', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('pack-ctx')['key'];

    try {
        WiseKnowledgeItem::create([
            'wise_api_key_id' => $key->id,
            'type' => KnowledgeSchema::KIND_FAQ,
            'scope' => KnowledgeSchema::SCOPE_MERCHANT,
            'title' => 'return policy',
            'question' => 'রিটার্ন কেমন?',
            'answer' => '৭ দিনে রিটার্ন সম্ভব শর্তসাপেক্ষে',
            'keywords' => ['রিটার্ন'],
            'status' => 'published',
            'version' => 1,
        ]);

        $pack = app(ContextPackBuilder::class)->build(
            $key,
            'রিটার্ন কেমন?',
            ['canonical' => 'রিটার্ন কেমন?', 'ambiguous' => []],
            ['intent' => 'unknown', 'confidence' => 40],
            null,
            [
                'thread' => ['summary' => 'কাস্টমার রিটার্ন জানতে চাইছে', 'open_issues' => ['return']],
                'funnel' => ['goal' => 'support', 'stage' => 'intent'],
                'order_id' => 'WC-100',
                'order_status' => 'processing',
                'signals' => ['emotion' => 'curious'],
                'candidates' => [['product_id' => '1', 'title' => 'Serum']],
            ],
            'pack-ctx-1',
        );

        expect($pack['conversation_summary'])->toContain('রিটার্ন')
            ->and($pack['goal'])->toBe('support')
            ->and($pack['tool_facts'])->not->toBeEmpty()
            ->and($pack['evidence_pack'])->not->toBeEmpty()
            ->and($pack['candidates'][0]['title'] ?? null)->toBe('Serum');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('skips grounded assist when feature flag is off', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('assist-flag-off')['key'];
    $meta = is_array($key->meta) ? $key->meta : [];
    $meta['governance'] = [
        'mode' => 'assist',
        'allow_auto' => false,
        'feature_flags' => ['llm_grounded_assist' => false],
        'policy_version' => 'merchant-test',
    ];
    $key->meta = $meta;
    $key->save();

    Http::fake();

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'asdfqwer nonsense utterance for gap',
            'channel' => 'test',
            'conversation_id' => 'assist-flag-off-1',
        ]));

        expect($run['decision']['source'] ?? null)->not->toBe('grounded_assist')
            ->and($run['decision']['grounded_assist'] ?? null)->toBeNull();
        Http::assertNothingSent();
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('applies grounded assist on unknown soft miss with mocked OpenAI', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('assist-apply')['key'];

    putenv('WISE_OPENAI_API_KEY=sk-test-grounded-assist');
    $_ENV['WISE_OPENAI_API_KEY'] = 'sk-test-grounded-assist';

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reasoning' => 'no evidence — clarify',
                        'plan' => ['clarify'],
                        'reply' => 'একটু পরিষ্কার করে বলবেন? কোন প্রোডাক্টের কথা বলছেন?',
                        'need_clarify' => true,
                        'confidence' => 96,
                        'score' => 9.1,
                        'used_knowledge_ids' => [],
                        'intent_refined' => 'unknown',
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'asdfqwerzxcv nonsense utterance xyz',
            'channel' => 'test',
            'conversation_id' => 'assist-apply-1',
            'context' => [
                'thread' => ['summary' => 'unclear ask'],
                'funnel' => ['goal' => 'information'],
            ],
        ]));

        expect($run['decision']['source'] ?? null)->toBe('grounded_assist_clarify')
            ->and($run['decision']['gap'] ?? true)->toBeFalse()
            ->and($run['decision']['suggested_reply'] ?? null)->toContain('প্রোডাক্ট')
            ->and($run['decision']['grounded_assist']['applied'] ?? false)->toBeTrue()
            ->and($run['decision']['grounded_assist']['passed_bar'] ?? false)->toBeTrue()
            ->and($run['turn']->evidence['answer'] ?? null)->toContain('প্রোডাক্ট')
            ->and($run['turn']->trace['P6_grounded_assist']['applied'] ?? false)->toBeTrue()
            ->and($run['turn']->decision['dialogue']['script_applied'] ?? false)->toBeFalse();
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
        putenv('WISE_OPENAI_API_KEY');
        unset($_ENV['WISE_OPENAI_API_KEY']);
    }
});

it('does not whitelist fee digits from chunk id or score metadata', function () {
    $guard = new \App\WiseAi\Language\LlmReplyGuard;
    $ok = $guard->accepts('', 'ডেলিভারি ৬০ টাকা', [
        'chunks' => [
            ['id' => 60, 'score' => 192, 'title' => 'delivery', 'answer' => 'এলাকা বললে চার্জ বলব'],
        ],
    ]);

    expect($ok)->toBeFalse();
});

it('discards below-bar factual assist and keeps gap path', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('assist-below-bar')['key'];

    putenv('WISE_OPENAI_API_KEY=sk-test-grounded-assist');
    $_ENV['WISE_OPENAI_API_KEY'] = 'sk-test-grounded-assist';

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reasoning' => 'weak',
                        'plan' => ['answer'],
                        'reply' => 'হ্যাঁ কাজ করে ভালোভাবে।',
                        'need_clarify' => false,
                        'confidence' => 70,
                        'score' => 6.0,
                        'used_knowledge_ids' => [],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'asdfqwerzxcv nonsense utterance xyz',
            'channel' => 'test',
            'conversation_id' => 'assist-below-bar-1',
        ]));

        expect($run['decision']['source'] ?? null)->not->toBe('grounded_assist')
            ->and($run['decision']['grounded_assist'] ?? null)->toBeNull()
            ->and($run['turn']->trace['P6_grounded_assist']['discard_reason'] ?? null)->toBe('below_bar');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
        putenv('WISE_OPENAI_API_KEY');
        unset($_ENV['WISE_OPENAI_API_KEY']);
    }
});

it('creates continuous learning draft after high-score grounded assist', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('assist-cl')['key'];

    putenv('WISE_OPENAI_API_KEY=sk-test-grounded-assist');
    $_ENV['WISE_OPENAI_API_KEY'] = 'sk-test-grounded-assist';

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'reasoning' => 'hours from pack',
                        'plan' => ['answer'],
                        'reply' => 'সকাল দশটা থেকে রাত আটটা পর্যন্ত খোলা।',
                        'need_clarify' => false,
                        'confidence' => 97,
                        'score' => 9.5,
                        'used_knowledge_ids' => [],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ], 200),
    ]);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'asdfqwer shop hours nonsense',
            'channel' => 'test',
            'conversation_id' => 'assist-cl-1',
        ]));

        expect($run['decision']['source'] ?? null)->toBe('grounded_assist')
            ->and($run['decision']['grounded_assist']['applied'] ?? false)->toBeTrue()
            ->and($run['decision']['learning']['cl_draft_id'] ?? null)->not->toBeNull();

        $draft = WiseKnowledgeItem::query()->find($run['decision']['learning']['cl_draft_id']);
        expect($draft)->not->toBeNull()
            ->and($draft->status)->toBe('draft')
            ->and($draft->meta['source'] ?? null)->toBe(\App\WiseAi\Learning\ConversationLearningExtractor::META_SOURCE);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
        putenv('WISE_OPENAI_API_KEY');
        unset($_ENV['WISE_OPENAI_API_KEY']);
    }
});

it('detects contradictory price-like values across chunks', function () {
    $hits = app(ContradictionDetector::class)->find([
        ['id' => 1, 'title' => 'faq', 'answer' => 'দাম ৫০০ টাকা'],
        ['id' => 2, 'title' => 'product', 'answer' => 'price 650 tk'],
    ]);

    expect($hits)->not->toBeEmpty()
        ->and($hits[0]['field'])->toBe('price_like');
});

it('tool decision collects order facts from context', function () {
    $facts = app(ToolDecision::class)->collect([
        'order_id' => '99',
        'order' => ['status' => 'shipped', 'tracking' => 'SF123'],
    ]);

    expect(collect($facts)->pluck('key')->all())->toContain('order_id', 'status', 'tracking');
});

it('training pack composer splits knowledge personality style rules', function () {
    $composed = app(TrainingPackComposer::class)->compose([
        'evidence_pack' => [
            ['id' => 1, 'type' => 'faq', 'title' => 'COD', 'answer' => 'ক্যাশ অন ডেলিভারি আছে'],
        ],
        'tool_facts' => [],
        'rules_slice' => ['If product unknown → ask.'],
    ]);

    expect($composed['knowledge'])->toContain('COD')
        ->and($composed['personality'])->not->toBeEmpty()
        ->and($composed['conversation_style'])->toContain('markdown')
        ->and($composed['decision_rules'])->toContain('unknown')
        ->and($composed['prompt_version'])->not->toBeEmpty();
});

it('keeps strong published FAQ as knowledge source without requiring assist', function () {
    KnowledgeResolver::excludePlatform(true);
    $key = WiseApiKey::generate('strong-faq')['key'];
    $q = 'এটা কি সত্যি কাজ করে?';
    $answer = 'আপনি কোন প্রোডাক্ট সম্পর্কে জানতে চান?';

    Http::fake();

    try {
        WiseKnowledgeItem::create([
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
            'conversation_id' => 'strong-faq-1',
        ]));

        expect($run['decision']['source'] ?? null)->toBe('knowledge')
            ->and($run['decision']['suggested_reply'] ?? null)->toBe($answer);
        Http::assertNothingSent();
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});
