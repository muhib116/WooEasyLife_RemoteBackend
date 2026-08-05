<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseKnowledgeItem;
use App\WiseAi\Contracts\IncomingTurn;
use App\WiseAi\DecideEngine;
use App\WiseAi\KnowledgeResolver;
use App\WiseAi\TurnRunner;
use App\WiseAi\Voice\VoiceContractBuilder;

it('omits decision.voice on chat channel without output_profile', function () {
    $key = WiseApiKey::generate('voice-omit-chat')['key'];

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dam koto?',
        'channel' => 'messenger',
        'conversation_id' => 'voice-omit-1',
    ]));

    expect($run['decision'])->not->toHaveKey('voice')
        ->and($run['decision']['brain_version'])->toBe(DecideEngine::BRAIN_VERSION);

    $key->delete();
});

it('attaches ask_slot product for bare price on channel=voice', function () {
    $key = WiseApiKey::generate('voice-bare-price')['key'];
    KnowledgeResolver::excludePlatform(true);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'dam koto?',
            'channel' => 'voice',
            'conversation_id' => 'voice-bare-1',
        ]));

        expect($run['decision']['action'])->toBe('clarify')
            ->and($run['decision']['voice']['next_action'])->toBe('ask_slot')
            ->and($run['decision']['voice']['slot_to_ask'])->toBe('product')
            ->and($run['decision']['voice']['gap'])->toBeFalse()
            ->and(mb_strlen((string) $run['decision']['voice']['speak_text']))->toBeLessThanOrEqual(VoiceContractBuilder::MAX_SPEAK_CHARS)
            ->and(trim((string) $run['decision']['voice']['speak_text']))->not->toBe('');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('attaches voice via context.output_profile without voice channel', function () {
    $key = WiseApiKey::generate('voice-profile-ctx')['key'];
    KnowledgeResolver::excludePlatform(true);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'dam koto?',
            'channel' => 'messenger',
            'conversation_id' => 'voice-profile-1',
            'context' => ['output_profile' => 'voice'],
        ]));

        expect($run['decision']['voice']['next_action'])->toBe('ask_slot')
            ->and($run['decision']['voice']['slot_to_ask'])->toBe('product');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('maps knowledge miss gap to transfer_human on voice', function () {
    $key = WiseApiKey::generate('voice-gap')['key'];
    KnowledgeResolver::excludePlatform(true);

    try {
        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'delivery charge koto?',
            'channel' => 'voice',
            'conversation_id' => 'voice-gap-1',
        ]));

        expect($run['decision']['gap'])->toBeTrue()
            ->and($run['decision']['action'])->toBe('needs_human')
            ->and($run['decision']['voice']['next_action'])->toBe('transfer_human')
            ->and($run['decision']['voice']['slot_to_ask'])->toBeNull()
            ->and($run['decision']['voice']['gap'])->toBeTrue()
            ->and(trim((string) $run['decision']['voice']['speak_text']))->not->toBe('');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('maps knowledge hit to continue with shortened speak_text', function () {
    $key = WiseApiKey::generate('voice-hit')['key'];
    KnowledgeResolver::excludePlatform(true);

    try {
        WiseKnowledgeItem::create([
            'wise_api_key_id' => $key->id,
            'type' => 'faq',
            'scope' => 'merchant',
            'status' => 'published',
            'title' => 'Delivery charge',
            'question' => 'delivery charge koto',
            'answer' => 'ঢাকার ভিতরে ডেলিভারি চার্জ ৬০ টাকা। ঢাকার বাইরে চার্জ এলাকা অনুযায়ী। বিস্তারিত জানতে এলাকা লিখে পাঠান। আরও জানতে চাইলে বলুন।',
            'keywords' => ['delivery', 'charge', 'ডেলিভারি'],
            'version' => 1,
        ]);

        $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
            'text' => 'delivery charge koto?',
            'channel' => 'voice',
            'conversation_id' => 'voice-hit-1',
        ]));

        expect($run['decision']['action'])->toBe('suggest_reply')
            ->and($run['decision']['source'])->toBe('knowledge')
            ->and($run['decision']['voice']['next_action'])->toBe('continue')
            ->and($run['decision']['voice']['gap'])->toBeFalse()
            ->and(mb_strlen((string) $run['decision']['voice']['speak_text']))->toBeLessThanOrEqual(VoiceContractBuilder::MAX_SPEAK_CHARS);
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});

it('maps thanks social reply to end on voice', function () {
    $key = WiseApiKey::generate('voice-thanks')['key'];

    $run = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'dhonnobad',
        'channel' => 'voice',
        'conversation_id' => 'voice-thanks-1',
    ]));

    expect($run['decision']['intent'])->toBe('thanks')
        ->and($run['decision']['voice']['next_action'])->toBe('end');

    $key->delete();
});

it('shortens long replies without inventing digits when building alone', function () {
    $builder = app(VoiceContractBuilder::class);
    $voice = $builder->build([
        'action' => 'suggest_reply',
        'intent' => 'delivery',
        'gap' => false,
        'suggested_reply' => str_repeat('বিস্তারিত তথ্য। ', 40).'শেষ বাক্য।',
    ]);

    expect($voice['next_action'])->toBe('continue')
        ->and(mb_strlen($voice['speak_text']))->toBeLessThanOrEqual(VoiceContractBuilder::MAX_SPEAK_CHARS);
});
