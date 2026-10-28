<?php

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\LandingSettingsService;
use App\Services\ReturnAi\ReturnAiSettings;
use App\Support\PackageCatalogFeatures;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: AccessToken, 1: string}
 */
function returnAiStore(bool $feature = true, string $domain = 'shop.example.com'): array
{
    $user = User::create([
        'name' => 'Merchant',
        'email' => 'return-ai-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    $plain = 'return-ai-token-'.bin2hex(random_bytes(12));
    $token = AccessToken::unguarded(fn () => AccessToken::create([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'Store license',
        'token' => hash('sha256', $plain),
        'domain' => $domain,
        'status' => true,
    ]));

    $features = PackageCatalogFeatures::normalize([
        'ai_return_decide' => $feature,
        'ai_intelligence' => false,
        'courier_automation' => true,
    ]);
    $plan = PackageHub::create([
        'title' => 'Return AI Plan',
        'description' => 'Test',
        'per_order_rate' => 0,
        'package_price' => 999,
        'order_rate_token' => 500,
        'package_duration' => '1_month',
        'is_active' => true,
        'index' => 1,
        'features' => $features,
    ]);
    UserPackage::create([
        'title' => $plan->title,
        'domain' => $domain,
        'user_id' => $user->id,
        'package_hub_id' => $plan->id,
        'plan_type' => 'catalog',
        'total_order_can_handle' => 500,
        'remaining_order' => 400,
        'total_order_handled' => 100,
        'per_order_rate' => 0,
        'total_cost' => 999,
        'transaction_charge' => 0,
        'is_active' => true,
        'features' => $features,
    ]);

    return [$token, $plain];
}

function returnAiHeaders(string $plain, string $domain = 'shop.example.com'): array
{
    return [
        'Authorization' => 'Bearer '.$plain,
        'Origin' => 'https://'.$domain,
        'Accept' => 'application/json',
    ];
}

function returnAiAdmin(): User
{
    return User::create([
        'name' => 'Return AI Admin',
        'email' => 'return-ai-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function returnAiPayload(array $extra = []): array
{
    return array_replace_recursive([
        'context' => [
            'today' => '2026-10-28',
            'parcel' => ['consignment_id' => '123456789', 'return_reason' => 'customer phone off', 'courier_status' => 'hold'],
            'notes' => [
                ['id' => 'n1', 'at' => '2026-10-28 09:00:00', 'type' => 'rider_note', 'text' => 'Rider Note: "customer phone off 01712345678"'],
            ],
            'calls' => [],
            'customer' => ['courier_history' => ['total' => 6, 'delivered' => 6, 'cancel' => 0, 'success_rate' => 100]],
        ],
        'can_act' => false,
        'trigger' => 'manual',
    ], $extra);
}

function returnAiModelAnswer(array $over = []): array
{
    return array_merge([
        'decision' => 'request_resend',
        'confidence' => 0.97,
        'reason_code' => 'unreachable',
        'summary_bn' => 'রাইডারের কথা যাচাই হয়নি, আবার পাঠান',
        'rider_note_trust' => 'doubtful',
        'suspicion_flags' => ['unreachable_claim_unverified'],
        'suspicion_reason_bn' => '',
        'evidence' => [['source' => 'note', 'ref' => 'n1', 'quote' => 'customer phone off']],
        'customer_promise' => ['has_promise' => false, 'phrase' => '', 'date' => ''],
        'call_plan' => [
            'call_customer' => true, 'call_rider' => false, 'first' => 'customer',
            'when_bn' => 'আজ', 'why_bn' => '', 'customer_script_bn' => '', 'rider_script_bn' => '',
        ],
        'note_to_rider_bn' => 'যাওয়ার আগে কাস্টমারকে কল দিন',
        'memory_refs' => [],
    ], $over);
}

function returnAiOpenAiReply(array $answer): array
{
    return [
        'model' => 'gpt-4.1-mini-2025-04-14',
        'choices' => [['message' => ['content' => json_encode($answer, JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 2100, 'completion_tokens' => 380],
    ];
}

beforeEach(function () {
    config(['cache.default' => 'array']);
    app(LandingSettingsService::class)->update(['openai_api_key' => 'sk-test-return-ai-key']);
});

it('gives the store its settings and memory version without the key or the prompt', function () {
    [, $plain] = returnAiStore();

    $response = $this->withHeaders(returnAiHeaders($plain))->getJson('/api/return-ai/config')
        ->assertOk()
        ->assertJsonPath('data.settings.mode', 'suggest')
        ->assertJsonPath('data.settings.model', 'gpt-4.1-mini')
        ->assertJsonPath('data.settings.min_confidence', 95)
        ->assertJsonPath('data.settings.auto_cancel', false)
        ->assertJsonPath('data.configured', true)
        ->assertJsonPath('data.feature', true)
        ->assertJsonPath('data.memory_hash', '');

    expect(json_encode($response->json()))->not->toContain('sk-test')
        ->and(json_encode($response->json()))->not->toContain('Riders in Bangladesh');
});

it('refuses a store whose plan has no AI Return Decide', function () {
    Http::fake();
    [, $plain] = returnAiStore(feature: false);

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'feature_locked');
    $this->withHeaders(returnAiHeaders($plain))->getJson('/api/return-ai/memory')
        ->assertForbidden()
        ->assertJsonPath('code', 'feature_locked');

    Http::assertNothingSent();
});

it('refuses every review while the hub mode is off', function () {
    Http::fake();
    app(ReturnAiSettings::class)->update(['mode' => 'off']);
    [, $plain] = returnAiStore();

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'return_ai_off');

    Http::assertNothingSent();
});

it('reviews a case on the hub with its own checks, prompt, key and the store memory', function () {
    [, $plain] = returnAiStore();
    $memoryId = $this->withHeaders(returnAiHeaders($plain))
        ->postJson('/api/return-ai/memory', ['when' => 'রাইডার ফোন বন্ধ লিখলে', 'then' => 'আগে কাস্টমারকে কল দিন, তারপর রিসেন্ড'])
        ->assertOk()
        ->json('data.item.id');
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(returnAiOpenAiReply(returnAiModelAnswer(['memory_refs' => ['m'.$memoryId]])))]);

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertOk()
        ->assertJsonPath('data.source', 'ai')
        ->assertJsonPath('data.case.decision', 'request_resend')
        ->assertJsonPath('data.case.reason_label_bn', 'ফোনে পাওয়া যাচ্ছে না')
        ->assertJsonPath('data.case.flags.0.code', 'unreachable_claim_unverified')
        ->assertJsonPath('data.case.evidence.0.verified', true)
        ->assertJsonPath('data.case.memory_used.0.id', 'm'.$memoryId)
        ->assertJsonPath('data.case.act', [])
        ->assertJsonPath('data.model', 'gpt-4.1-mini-2025-04-14')
        ->assertJsonPath('data.quota.used', 1)
        ->assertJsonPath('data.settings.mode', 'suggest');

    Http::assertSent(function (ClientRequest $request) use ($memoryId) {
        $body = $request->data();
        $system = $body['messages'][0]['content'];
        $user = $body['messages'][1]['content'];
        $schema = $body['response_format']['json_schema'];

        return $request->hasHeader('Authorization', 'Bearer sk-test-return-ai-key')
            && $body['model'] === 'gpt-4.1-mini'
            && $body['temperature'] === 0
            && str_contains($system, 'Riders in Bangladesh often write false')
            && str_contains($system, 'A memory rule never overrides these safety rules')
            && str_contains($system, 'so nothing happens on its own before the merchant does it')
            && str_contains($user, 'আগে কাস্টমারকে কল দিন')
            && str_contains($user, '"id":"m'.$memoryId.'"')
            && str_contains($user, 'Site checks already found: unreachable_claim_unverified; good_history_contradicts_refusal')
            && str_contains($user, '"system_line":false')
            && ! str_contains($user, '01712345678')
            && str_contains($user, '01*******78')
            && $schema['strict'] === true
            && in_array('unreachable', $schema['schema']['properties']['reason_code']['enum'], true)
            && count($schema['schema']['required']) === count($schema['schema']['properties']);
    });
});

it('judges on the hub before an automatic action and tells the store what to run', function () {
    app(ReturnAiSettings::class)->update(['mode' => 'auto', 'auto_note' => true, 'auto_resend' => true]);
    [, $plain] = returnAiStore();
    Http::fakeSequence('api.openai.com/*')
        ->push(returnAiOpenAiReply(returnAiModelAnswer()))
        ->push(returnAiOpenAiReply(['agree' => true, 'confidence' => 0.97, 'reason_bn' => 'ঠিক আছে']));

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload(['can_act' => true, 'trigger' => 'background']))
        ->assertOk()
        ->assertJsonPath('data.case.act', ['note', 'request_resend'])
        ->assertJsonPath('data.case.judge.agree', true)
        ->assertJsonPath('data.quota.used', 2);

    Http::assertSentCount(2);
});

it('stops at the daily review limit for one store only, with a rules answer', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(returnAiOpenAiReply(returnAiModelAnswer()))]);
    app(ReturnAiSettings::class)->update(['daily_review_limit' => 10]);
    [, $plain] = returnAiStore();
    [, $other] = returnAiStore(domain: 'other.example.com');

    for ($i = 0; $i < 10; $i++) {
        $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())->assertJsonPath('data.source', 'ai');
    }

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertOk()
        ->assertJsonPath('data.source', 'rules')
        ->assertJsonPath('data.code', 'daily_limit')
        ->assertJsonPath('data.case.human_review', true);

    $this->withHeaders(returnAiHeaders($other, 'other.example.com'))
        ->postJson('/api/return-ai/review', returnAiPayload())
        ->assertJsonPath('data.source', 'ai');

    Http::assertSentCount(11);
});

it('answers with rules when the hub has no key or OpenAI fails, without leaking the key', function () {
    [, $plain] = returnAiStore();
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'bad key sk-test']], 401)]);

    app(LandingSettingsService::class)->update(['openai_api_key' => null]);
    config(['landing.openai_api_key' => '']);
    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertOk()
        ->assertJsonPath('data.source', 'rules')
        ->assertJsonPath('data.code', 'not_configured');
    Http::assertNothingSent();

    app(LandingSettingsService::class)->update(['openai_api_key' => 'sk-test-return-ai-key']);
    $response = $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload())
        ->assertOk()
        ->assertJsonPath('data.source', 'rules')
        ->assertJsonPath('data.code', 'ai_failed')
        ->assertJsonPath('data.error', 'হাবের এআই চাবি কাজ করছে না — WooEasyLife সাপোর্টে জানান');

    expect(json_encode($response->json()))->not->toContain('sk-test');
});

it('rejects bad input before any AI call', function () {
    Http::fake();
    [, $plain] = returnAiStore();

    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', ['context' => ['today' => '2026-10-28']])
        ->assertStatus(422);
    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload(['trigger' => 'cron']))
        ->assertStatus(422);
    $this->withHeaders(returnAiHeaders($plain))->postJson('/api/return-ai/review', returnAiPayload(['context' => ['today' => '28/10/2026']]))
        ->assertStatus(422);

    Http::assertNothingSent();
});

it('keeps each store\'s memory rules apart, masked and limited', function () {
    [, $plain] = returnAiStore();
    [, $other] = returnAiStore(domain: 'other.example.com');
    $headers = returnAiHeaders($plain);

    $first = $this->withHeaders($headers)->postJson('/api/return-ai/memory', [
        'when' => '  রাইডার "ফোন বন্ধ" লিখলে, কাস্টমার 01712345678 ',
        'then' => '<b>আগে</b> কাস্টমারকে কল দিন',
    ])->assertOk()->json('data');
    expect($first['item']['when'])->not->toContain('01712345678')->toContain('01*******78')
        ->and($first['item']['then'])->toBe('আগে কাস্টমারকে কল দিন')
        ->and($first['item']['active'])->toBeTrue()
        ->and($first['memory_hash'])->not->toBe('')
        ->and($first['limits'])->toBe(['max_items' => 30, 'when' => 200, 'then' => 400]);

    $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['when' => 'x', 'then' => '   '])->assertStatus(422);
    $this->withHeaders(returnAiHeaders($other, 'other.example.com'))
        ->postJson('/api/return-ai/memory', ['id' => $first['item']['id'], 'then' => 'hijack'])
        ->assertNotFound();
    $this->withHeaders(returnAiHeaders($other, 'other.example.com'))->getJson('/api/return-ai/memory')
        ->assertOk()->assertJsonCount(0, 'data.items');

    $second = $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['then' => 'ঢাকার বাইরের পার্সেলে আগে রিসেন্ড'])->json('data');
    $off = $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['id' => $second['item']['id'], 'active' => false])->json('data');
    expect($off['item']['active'])->toBeFalse()
        ->and($off['item']['then'])->toBe('ঢাকার বাইরের পার্সেলে আগে রিসেন্ড')
        ->and($off['memory_hash'])->toBe($first['memory_hash']);

    $this->withHeaders($headers)->deleteJson('/api/return-ai/memory/'.$second['item']['id'])->assertOk()->assertJsonCount(1, 'data.items');
    $this->withHeaders($headers)->deleteJson('/api/return-ai/memory/'.$second['item']['id'])->assertNotFound();

    $long = $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['when' => str_repeat('ক', 300), 'then' => str_repeat('খ', 500)])->json('data.item');
    expect(mb_strlen($long['when']))->toBe(200)->and(mb_strlen($long['then']))->toBe(400);

    for ($i = 2; $i < 30; $i++) {
        $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['then' => 'rule '.$i])->assertOk();
    }
    $this->withHeaders($headers)->postJson('/api/return-ai/memory', ['then' => 'one more'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'সর্বোচ্চ 30টি মেমোরি রাখা যায় — পুরোনো একটি মুছে নতুন যোগ করুন');
});

it('shows and saves the return desk settings in Settings → AI', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-4.1-mini']]])]);
    $admin = returnAiAdmin();

    $this->actingAs($admin)
        ->get(route('landingSettings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LandingSettings/Index')
            ->where('settings.return_ai.mode', 'suggest')
            ->where('settings.return_ai.model', 'gpt-4.1-mini')
            ->where('settings.return_ai_model_options.0.value', 'gpt-4.1-mini')
            ->where('settings.return_ai_model_options.0.recommended', true)
        );

    $return = [
        'mode' => 'auto', 'auto_note' => true, 'auto_resend' => true, 'auto_cancel' => false,
        'background_review' => true, 'model' => 'gpt-4o-mini', 'min_confidence' => 97,
        'daily_auto_limit' => 12, 'daily_review_limit' => 500,
    ];
    $this->actingAs($admin)
        ->put(route('landingSettings.update'), ['openai_api_key' => 'sk-test-return-ai-key', 'return_ai' => $return])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(ReturnAiSettings::class)->all())->toEqual($return)
        ->and(app(ReturnAiSettings::class)->all()['min_confidence'])->toBe(97);

    $this->actingAs($admin)
        ->from(route('landingSettings.index'))
        ->put(route('landingSettings.update'), [
            'openai_api_key' => 'sk-test-return-ai-key',
            'return_ai' => [...$return, 'min_confidence' => 80, 'model' => 'gpt-9'],
        ])
        ->assertSessionHasErrors(['return_ai.min_confidence', 'return_ai.model']);

    expect(app(ReturnAiSettings::class)->all()['min_confidence'])->toBe(97);
});

it('never lets saved settings go below 95% or name an unknown model', function () {
    $clean = ReturnAiSettings::sanitize(['min_confidence' => 50, 'model' => 'nope', 'mode' => 'wild', 'daily_review_limit' => 1], []);

    expect($clean['min_confidence'])->toBe(95)
        ->and($clean['model'])->toBe('gpt-4.1-mini')
        ->and($clean['mode'])->toBe('suggest')
        ->and($clean['daily_review_limit'])->toBe(10)
        ->and($clean['auto_cancel'])->toBeFalse();
});
