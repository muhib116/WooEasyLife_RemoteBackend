<?php

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\Language\LlmLanguageConfig;
use App\WiseAi\Language\LlmLanguageSpecialist;
use App\WiseAi\Language\LlmReplyGuard;
use App\WiseAi\TurnRunner;
use App\WiseAi\Contracts\IncomingTurn;
use Illuminate\Support\Facades\Http;

it('digit guard rejects invented prices', function () {
    $guard = new LlmReplyGuard;
    expect($guard->accepts('দাম ৫০০ টাকা', 'দাম ৬০০ টাকা'))->toBeFalse();
    expect($guard->accepts('দাম ৫০০ টাকা', 'মূল্য ৫০০ টাকা ভাই'))->toBeTrue();
});

it('guard rejects injecting salam into a casual hello rewrite', function () {
    $guard = new LlmReplyGuard;
    expect($guard->accepts(
        'হ্যালো! কীভাবে সাহায্য করতে পারি?',
        'ওয়ালাইকুম আসসালাম! কীভাবে সাহায্য করতে পারি?',
    ))->toBeFalse();
    expect($guard->accepts(
        'ওয়ালাইকুম আসসালাম! কীভাবে সাহায্য করতে পারি?',
        'ওয়ালাইকুম আসসালাম! কীভাবে আপনাকে সাহায্য করতে পারি?',
    ))->toBeTrue();
});

it('llm skip when platform disabled', function () {
    $config = app(LlmLanguageConfig::class);
    $config->update(['enabled' => false]);

    $specialist = app(LlmLanguageSpecialist::class);
    $result = $specialist->maybeRewrite(
        ['suggested_reply' => 'হ্যালো!', 'source' => 'pattern'],
        ['feature_flags' => ['llm_language' => true]],
        null,
    );

    expect($result['language_llm']['applied'])->toBeFalse()
        ->and($result['language_llm']['reason'])->toBe('platform_off')
        ->and($result['decision']['suggested_reply'])->toBe('হ্যালো!');

    $config->update(['enabled' => true]);
});

it('llm skip when no key (fail-open)', function () {
    $config = app(LlmLanguageConfig::class);
    $config->update(['enabled' => true, 'api_key' => '__clear__']);

    // Ensure env fallback empty for this process.
    putenv('WISE_OPENAI_API_KEY');
    $_ENV['WISE_OPENAI_API_KEY'] = '';

    $specialist = app(LlmLanguageSpecialist::class);
    $result = $specialist->maybeRewrite(
        ['suggested_reply' => 'হ্যালো!', 'source' => 'pattern'],
        ['feature_flags' => ['llm_language' => true]],
        null,
    );

    expect($result['language_llm']['applied'])->toBeFalse()
        ->and($result['language_llm']['reason'])->toBe('no_key');
});

it('llm applies rewrite when mocked and guard passes', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'হ্যালো ভাই! কীভাবে সাহায্য করতে পারি?']],
            ],
        ], 200),
    ]);

    $config = app(LlmLanguageConfig::class);
    $config->update(['enabled' => true, 'api_key' => 'sk-test-wise-llm']);

    $specialist = app(LlmLanguageSpecialist::class);
    $result = $specialist->maybeRewrite(
        ['suggested_reply' => 'হ্যালো!', 'source' => 'pattern'],
        ['feature_flags' => ['llm_language' => true]],
        null,
    );

    expect($result['language_llm']['applied'])->toBeTrue()
        ->and($result['decision']['suggested_reply'])->toContain('হ্যালো');

    $config->update(['api_key' => '__clear__']);
});

it('decide seals language_llm and experience without requiring openai', function () {
    $gen = WiseApiKey::generate('llm-exp-decide');
    $key = $gen['key'];

    app(LlmLanguageConfig::class)->update(['enabled' => true, 'api_key' => '__clear__']);

    $result = app(TurnRunner::class)->run($key, IncomingTurn::fromPayload([
        'text' => 'hello',
        'channel' => 'test',
        'conversation_id' => 'llm-exp-1',
        'context' => [],
    ]));

    $decision = $result['decision'];
    expect($decision)->toHaveKey('language_llm')
        ->and($decision['language_llm']['applied'])->toBeFalse()
        ->and($decision)->toHaveKey('experience')
        ->and($result['turn']->config_snapshot['experience_version'] ?? null)->not->toBeNull()
        ->and($result['turn']->config_snapshot['brain_version'] ?? null)->toBe(\App\WiseAi\DecideEngine::BRAIN_VERSION);

    $key->delete();
});
