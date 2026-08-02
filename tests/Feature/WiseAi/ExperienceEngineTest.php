<?php

use App\Models\WiseAi\WiseApiKey;
use App\Models\WiseAi\WiseExperienceSignal;
use App\WiseAi\Experience\ExperienceRecorder;
use App\WiseAi\Experience\ExperienceResolver;
use App\WiseAi\TurnRunner;
use App\WiseAi\Contracts\IncomingTurn;

it('records experience from feedback and soft-nudges later turns', function () {
    $gen = WiseApiKey::generate('exp-engine');
    $plain = $gen['plain'];
    $key = $gen['key'];

    $runner = app(TurnRunner::class);
    $first = $runner->run($key, IncomingTurn::fromPayload([
        'text' => 'hello',
        'channel' => 'test',
        'conversation_id' => 'exp-1',
        'context' => [],
    ]));

    $this->postJson('/api/wise/v1/feedback', [
        'turn_id' => $first['turn']->id,
        'outcome' => 'approved',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertOk();

    expect(WiseExperienceSignal::query()->where('wise_api_key_id', $key->id)->count())->toBe(1);

    $this->postJson('/api/wise/v1/experience', [
        'signal_type' => 'external',
        'intent' => 'greeting',
        'weight' => 2,
        'idempotency_key' => 'ext-greeting-1',
        'pattern_key' => 'script:demo',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertCreated()
        ->assertJsonPath('ok', true);

    $hint = app(ExperienceResolver::class)->apply($key, [
        'intent' => 'greeting',
        'action' => 'suggest_reply',
        'source' => 'pattern',
        'confidence' => 80,
        'suggested_reply' => 'হ্যালো!',
    ], [
        'feature_flags' => ['experience_engine' => true],
    ]);

    expect($hint['experience']['matches'])->toBeGreaterThan(0)
        ->and($hint['experience']['applied'])->toBeTrue();

    // Knowledge source must keep suggested_reply untouched by style assist_hint path.
    $knowledge = app(ExperienceResolver::class)->apply($key, [
        'intent' => 'price',
        'action' => 'suggest_reply',
        'source' => 'knowledge',
        'confidence' => 90,
        'suggested_reply' => 'দাম ৫০০ টাকা',
    ], [
        'feature_flags' => ['experience_engine' => true],
    ]);
    expect($knowledge['decision']['suggested_reply'])->toBe('দাম ৫০০ টাকা');

    $key->delete();
});

it('experience api rejects invalid key', function () {
    $this->postJson('/api/wise/v1/experience', [
        'signal_type' => 'external',
    ])->assertUnauthorized();
});

it('does not let null-intent signals pollute other intents', function () {
    $gen = WiseApiKey::generate('exp-intent-scope');
    $plain = $gen['plain'];
    $key = $gen['key'];

    $this->postJson('/api/wise/v1/experience', [
        'signal_type' => 'external',
        'weight' => 5,
        'idempotency_key' => 'null-intent-pollute',
        // no intent
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertCreated();

    $hint = app(ExperienceResolver::class)->preview($key, [
        'intent' => 'price',
        'action' => 'clarify',
        'source' => 'pattern',
        'confidence' => 80,
    ], [
        'feature_flags' => ['experience_engine' => true],
    ]);

    expect($hint['matches'])->toBe(0)
        ->and($hint['preferred_script'])->toBeNull();

    $wildcard = app(ExperienceResolver::class)->preview($key, [
        'intent' => 'price',
        'action' => 'clarify',
        'source' => 'pattern',
        'confidence' => 80,
    ], [
        'feature_flags' => ['experience_engine' => true],
    ]);

    $this->postJson('/api/wise/v1/experience', [
        'signal_type' => 'external',
        'intent' => '*',
        'weight' => 3,
        'idempotency_key' => 'wildcard-intent',
        'pattern_key' => 'script:soft_clarify_unknown.clarify',
    ], [
        'Authorization' => 'Bearer '.$plain,
    ])->assertCreated();

    $wildcard = app(ExperienceResolver::class)->preview($key, [
        'intent' => 'price',
        'action' => 'clarify',
        'source' => 'pattern',
        'confidence' => 80,
    ], [
        'feature_flags' => ['experience_engine' => true],
    ]);

    expect($wildcard['matches'])->toBe(1)
        ->and($wildcard['preferred_script'])->toBe('soft_clarify_unknown.clarify');

    $key->delete();
});

it('applies preferred clarify script before dialogue enrich', function () {
    $gen = WiseApiKey::generate('exp-pref-script');
    $key = $gen['key'];

    // Seed enough weight for preferred_script threshold (>= 2).
    app(ExperienceRecorder::class)->fromExternal($key, [
        'signal_type' => 'external',
        'intent' => 'price',
        'action' => 'clarify',
        'weight' => 3,
        'pattern_key' => 'script:soft_clarify_unknown.clarify',
        'idempotency_key' => 'pref-soft-1',
    ]);

    $preview = app(ExperienceResolver::class)->preview($key, [
        'intent' => 'price',
        'action' => 'clarify',
        'source' => 'pattern',
        'confidence' => 70,
        'dialogue' => ['id' => 'ask_price_bare'],
    ], ['feature_flags' => ['experience_engine' => true]]);

    expect($preview['preferred_script'])->toBe('soft_clarify_unknown.clarify');

    $enriched = app(\App\WiseAi\Dialogue\DialogueScripts::class)->enrich([
        'intent' => 'price',
        'action' => 'clarify',
        'source' => 'pattern',
        'confidence' => 70,
        'dialogue' => ['id' => 'ask_price_bare'],
        'suggested_reply' => 'old',
    ], [
        'preferred_script' => $preview['preferred_script'],
    ]);

    expect($enriched['dialogue']['script']['id'] ?? null)->toBe('soft_clarify_unknown.clarify')
        ->and($enriched['dialogue']['experience_preferred_script'] ?? null)->toBe('soft_clarify_unknown.clarify')
        ->and($enriched['suggested_reply'])->toContain('পরিষ্কার');

    $key->delete();
});
