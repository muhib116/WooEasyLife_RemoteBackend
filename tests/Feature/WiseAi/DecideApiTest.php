<?php

use App\Models\WiseAi\WiseApiKey;
use App\WiseAi\DecideEngine;
use App\WiseAi\KnowledgeResolver;

it('rejects decide without an api key', function () {
    $this->postJson('/api/wise/v1/decide', [
        'text' => 'hello',
    ])->assertUnauthorized();
});

it('decides mixed greeting+price over http as price', function () {
    $gen = WiseApiKey::generate('decide-smoke-price');
    $plain = $gen['plain'];
    $key = $gen['key'];

    $this->postJson('/api/wise/v1/decide', [
        'text' => 'hello dam koto',
        'channel' => 'test',
        'conversation_id' => 'decide-smoke-price-1',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('decision.intent', 'price')
        ->assertJsonPath('decision.brain_version', DecideEngine::BRAIN_VERSION)
        ->assertJson(fn ($json) => $json->whereType('turn_id', 'integer')->etc());

    $key->delete();
});

it('decides bare hello over http as social suggest_reply', function () {
    $gen = WiseApiKey::generate('decide-smoke-hello');
    $plain = $gen['plain'];
    $key = $gen['key'];

    $response = $this->postJson('/api/wise/v1/decide', [
        'text' => 'hello',
        'channel' => 'test',
        'conversation_id' => 'decide-smoke-hello-1',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('decision.intent', 'greeting')
        ->assertJsonPath('decision.action', 'suggest_reply')
        ->assertJsonPath('gap', false);

    expect(trim((string) $response->json('decision.suggested_reply')))->not->toBe('');

    $key->delete();
});

it('returns gap_assist soft reply on knowledge miss over http', function () {
    $gen = WiseApiKey::generate('decide-smoke-gap');
    $plain = $gen['plain'];
    $key = $gen['key'];
    KnowledgeResolver::excludePlatform(true);

    try {
        $response = $this->postJson('/api/wise/v1/decide', [
            'text' => 'delivery charge koto?',
            'channel' => 'test',
            'conversation_id' => 'decide-smoke-gap-1',
        ], [
            'Authorization' => 'Bearer '.$plain,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('gap', true)
            ->assertJsonPath('decision.action', 'needs_human')
            ->assertJsonPath('decision.source', 'gap_assist');

        expect(trim((string) $response->json('decision.suggested_reply')))->not->toBe('')
            ->and($response->json('decision.suggested_reply'))->not->toMatch('/^\d+\s*(tk|taka|৳)/i');
    } finally {
        KnowledgeResolver::excludePlatform(false);
        $key->delete();
    }
});
