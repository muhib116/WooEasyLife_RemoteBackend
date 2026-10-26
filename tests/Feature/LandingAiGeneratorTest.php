<?php

use App\Models\AccessToken;
use App\Models\LandingAiJob;
use App\Models\User;
use App\Services\LandingAi\OpenAiModelCatalog;
use App\Services\LandingAi\ReferencePageFetcher;
use App\Services\LandingSettingsService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

const LANDING_AI_TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function landingAiAdmin(): User
{
    return User::create([
        'name' => 'Landing AI Admin',
        'email' => 'landing-ai-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

/**
 * @return array{0: AccessToken, 1: string}
 */
function landingAiStore(string $domain = 'shop.example.com'): array
{
    $user = User::create([
        'name' => 'Merchant',
        'email' => 'landing-ai-merchant-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    $plain = 'landing-ai-token-'.bin2hex(random_bytes(12));
    $token = AccessToken::unguarded(fn () => AccessToken::create([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'Store license',
        'token' => hash('sha256', $plain),
        'domain' => $domain,
        'status' => true,
    ]));

    return [$token, $plain];
}

function landingAiHeaders(string $plain, string $domain = 'shop.example.com'): array
{
    return [
        'Authorization' => 'Bearer '.$plain,
        'Origin' => 'https://'.$domain,
        'Accept' => 'application/json',
    ];
}

function landingAiSpec(): array
{
    return [
        'fields' => [
            ['key' => 'product_name', 'label' => 'Headline', 'max' => 80],
            ['key' => 'hero_sub', 'label' => 'Short description', 'max' => 200, 'multiline' => true],
            ['key' => 'live_viewers_text', 'label' => 'Live viewers text', 'max' => 60, 'placeholder' => '{n}'],
            ['key' => 'atlas_pricing_now', 'label' => 'Now price', 'max' => 40],
        ],
        'lists' => [
            ['key' => 'review_items', 'label' => 'Reviews', 'min' => 3, 'max' => 5, 'fields' => [
                ['key' => 'name', 'label' => 'Name', 'max' => 40],
                ['key' => 'place', 'label' => 'District', 'max' => 40],
                ['key' => 'stars', 'label' => 'Stars', 'type' => 'integer', 'min' => 1, 'max' => 5],
                ['key' => 'text', 'label' => 'Review', 'max' => 220],
            ]],
            ['key' => 'trust_items', 'label' => 'Trust badges', 'min' => 3, 'max' => 3, 'fields' => [
                ['key' => 'icon', 'label' => 'Icon', 'options' => ['🚚', '💰', '🔒', '✅', '⭐']],
                ['key' => 'label', 'label' => 'Label', 'max' => 40],
            ]],
        ],
    ];
}

function landingAiPayload(array $extra = []): array
{
    return array_merge([
        'spec' => landingAiSpec(),
        'store' => ['brand' => 'Chander Alo', 'site_name' => 'Chander Alo Gallery', 'layout' => 'offer'],
        'products' => [[
            'name' => 'Leather Wallet',
            'price' => '৳990 (regular ৳1,290)',
            'categories' => ['Wallet'],
            'options' => ['Color: Black, Brown'],
            'in_stock' => true,
            'short_description' => 'Genuine leather, 8 card slots.',
        ]],
        'context' => 'Eid offer. Target: office workers.',
        'reference_urls' => ['https://ref-shop.example/wallet'],
        'images' => [LANDING_AI_TINY_PNG],
    ], $extra);
}

function landingAiModelsList(): array
{
    return ['data' => array_map(fn ($id) => ['id' => $id, 'object' => 'model'], [
        'gpt-5.1', 'gpt-5.1-2025-11-13', 'gpt-5', 'gpt-5-mini', 'gpt-4.1', 'gpt-4o',
        'gpt-image-1', 'whisper-1', 'o3',
    ])];
}

function landingAiAnswer(): array
{
    $reviews = [];
    foreach ([9, 4, 0, 5, 5, 5] as $i => $stars) {
        $reviews[] = ['name' => 'রহিম '.$i, 'place' => 'ঢাকা', 'stars' => $stars, 'text' => 'খুব ভালো মানের, সময়মতো পেয়েছি।'];
    }

    return [
        'texts' => [
            'product_name' => '<b>প্রিমিয়াম</b> লেদার ওয়ালেট',
            'hero_sub' => "হাতে পেয়ে টাকা দিন\n\n\n\nসারাদেশে ক্যাশ অন ডেলিভারি",
            'live_viewers_text' => 'এখন অনেকে দেখছেন',
            'atlas_pricing_now' => '',
        ],
        'lists' => [
            'review_items' => $reviews,
            'trust_items' => [
                ['icon' => '🚚', 'label' => 'দ্রুত ডেলিভারি'],
                ['icon' => '🔥', 'label' => 'ক্যাশ অন ডেলিভারি'],
                ['icon' => '🔒', 'label' => 'নিরাপদ অর্ডার'],
                ['icon' => '✅', 'label' => 'বাড়তি'],
            ],
        ],
        'notes' => 'Used the product facts and one reference page.',
    ];
}

function landingAiCompleted(string $id, array $answer): array
{
    return [
        'id' => $id,
        'object' => 'response',
        'status' => 'completed',
        'output' => [
            ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => []],
            ['type' => 'message', 'role' => 'assistant', 'content' => [
                ['type' => 'output_text', 'text' => json_encode($answer, JSON_UNESCAPED_UNICODE)],
            ]],
        ],
        'usage' => ['input_tokens' => 4200, 'output_tokens' => 3100],
    ];
}

function landingAiPublicFetcher(): void
{
    app()->instance(ReferencePageFetcher::class, new ReferencePageFetcher(fn () => ['93.184.216.34']));
}

beforeEach(function () {
    // config/cache.php reads CACHE_DRIVER, so phpunit's CACHE_STORE=array does not apply.
    // Without this, test keys and model lists land in the dev app's file cache.
    config(['cache.default' => 'array']);
    app(LandingSettingsService::class)->update(['openai_api_key' => 'sk-test-landing-ai-key']);
});

it('ranks and filters openai models for the landing generator', function () {
    $models = OpenAiModelCatalog::filter([
        'gpt-4o-2024-08-06', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano', 'gpt-5', 'gpt-5-mini',
        'gpt-5-nano', 'gpt-5.1', 'gpt-5.1-2025-11-13', 'gpt-5-pro', 'gpt-5-codex', 'gpt-realtime',
        'gpt-4o-audio-preview', 'gpt-image-1', 'text-embedding-3-large', 'o3', 'o4-mini', 'o1',
        'gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'dall-e-3', 'whisper-1', 'gpt-5-chat-latest',
        'gpt-4o-search-preview', 'omni-moderation-latest', 'gpt-4o-transcribe', 'gpt-oss-120b',
    ]);

    expect($models)->toBe([
        'gpt-5.1', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano',
        'gpt-4.1', 'gpt-4.1-mini', 'gpt-4.1-nano', 'gpt-4o',
        'o4-mini', 'o3',
    ])
        ->and(OpenAiModelCatalog::best($models))->toBe('gpt-5.1')
        ->and(OpenAiModelCatalog::best(['gpt-4o-mini', 'gpt-4.1', 'o3']))->toBe('gpt-4.1')
        ->and(OpenAiModelCatalog::isReasoning('gpt-5-mini'))->toBeTrue()
        ->and(OpenAiModelCatalog::isReasoning('gpt-4.1'))->toBeFalse();
});

it('falls back to the built-in model list when OpenAI cannot be reached', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response(['error' => ['message' => 'down']], 500)]);

    $catalog = app(OpenAiModelCatalog::class)->catalog(true);

    expect($catalog['source'])->toBe('fallback')
        ->and($catalog['best'])->toBe('gpt-5.1')
        ->and($catalog['models'])->toContain('gpt-4.1')
        ->and($catalog['error'])->toContain('HTTP 500');
});

it('shows the landing model list with auto as the best model in settings', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response(landingAiModelsList())]);

    $this->actingAs(landingAiAdmin())
        ->get(route('landingSettings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LandingSettings/Index')
            ->where('settings.landing_model_best', 'gpt-5.1')
            ->where('settings.landing_model_list_error', null)
            ->where('settings.landing_model_list_source', 'live')
            ->where('settings.landing_model_options', fn ($options) => count($options) === 7)
            ->where('settings.landing_model_options.0.value', '')
            ->where('settings.landing_model_options.0.label', 'Auto — gpt-5.1 (best available)')
            ->where('settings.landing_model_options.1.value', 'gpt-5.1')
            ->where('settings.openai_landing_model', null)
            ->where('settings.landing_ai_daily_limit', 20)
        );
});

it('saves the landing model and daily limit, and clears the model back to auto', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response(landingAiModelsList())]);
    $admin = landingAiAdmin();
    $settings = app(LandingSettingsService::class);

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => 'sk-test-landing-ai-key',
            'openai_landing_model' => 'gpt-5-mini',
            'landing_ai_daily_limit' => 7,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($settings->openaiLandingModel())->toBe('gpt-5-mini')
        ->and($settings->landingAiDailyLimit())->toBe(7)
        ->and(app(OpenAiModelCatalog::class)->effectiveModel())->toBe('gpt-5-mini');

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => 'sk-test-landing-ai-key',
            'openai_landing_model' => '',
            'landing_ai_daily_limit' => 7,
        ])
        ->assertRedirect();

    expect($settings->openaiLandingModel())->toBeNull()
        ->and(app(OpenAiModelCatalog::class)->effectiveModel())->toBe('gpt-5.1');
});

it('rejects an unknown landing model and an out-of-range daily limit', function () {
    Http::fake(['api.openai.com/v1/models' => Http::response(landingAiModelsList())]);

    $this->actingAs(landingAiAdmin())
        ->from(route('landingSettings.index'))
        ->put(route('landingSettings.update'), [
            'openai_landing_model' => 'gpt-9-imaginary',
            'landing_ai_daily_limit' => 0,
        ])
        ->assertRedirect(route('landingSettings.index'))
        ->assertSessionHasErrors(['openai_landing_model', 'landing_ai_daily_limit']);
});

it('generates landing copy through an OpenAI background response and cleans the answer', function () {
    landingAiPublicFetcher();
    Http::fake([
        'api.openai.com/v1/models' => Http::response(landingAiModelsList()),
        'api.openai.com/v1/responses' => Http::response(['id' => 'resp_123', 'object' => 'response', 'status' => 'queued']),
        'api.openai.com/v1/responses/resp_123' => Http::response(landingAiCompleted('resp_123', landingAiAnswer())),
        'ref-shop.example/*' => Http::response(
            '<html><head><title>Wallet Offer</title><meta name="description" content="Best leather wallet"></head>'
            .'<body><h1>চামড়ার ওয়ালেট</h1><p>ক্যাশ অন ডেলিভারি</p><script>var x=1;</script></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        ),
    ]);

    [$token, $plain] = landingAiStore();

    $start = $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload())
        ->assertStatus(202)
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.model', 'gpt-5.1')
        ->assertJsonPath('data.quota.used', 1)
        ->assertJsonPath('data.quota.limit', 20);

    Http::assertSent(function (ClientRequest $request) {
        if ($request->url() !== 'https://api.openai.com/v1/responses' || $request->method() !== 'POST') {
            return false;
        }
        $content = $request['input'][0]['content'];
        $schema = $request['text']['format']['schema'];

        return $request['background'] === true
            && $request['store'] === true
            && $request['model'] === 'gpt-5.1'
            && $request['reasoning']['effort'] === 'low'
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true
            && $schema['additionalProperties'] === false
            && $schema['required'] === ['texts', 'lists', 'notes']
            && $schema['properties']['texts']['required'] === ['product_name', 'hero_sub', 'live_viewers_text', 'atlas_pricing_now']
            && $schema['properties']['lists']['properties']['trust_items']['items']['properties']['icon']['enum'] === ['🚚', '💰', '🔒', '✅', '⭐']
            && str_contains($request['instructions'], 'The store brand is "Chander Alo"')
            && str_contains($content[0]['text'], 'Leather Wallet')
            && str_contains($content[0]['text'], 'Wallet Offer')
            && str_contains($content[0]['text'], 'চামড়ার ওয়ালেট')
            && ! str_contains($content[0]['text'], 'var x=1')
            && $content[1]['type'] === 'input_image'
            && str_starts_with($content[1]['image_url'], 'data:image/png;base64,');
    });

    $uuid = $start->json('data.job');

    $done = $this->withHeaders(landingAiHeaders($plain))
        ->getJson('/api/landing-ai/jobs/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.settings.product_name', 'প্রিমিয়াম লেদার ওয়ালেট')
        ->assertJsonPath('data.settings.hero_sub', "হাতে পেয়ে টাকা দিন\n\nসারাদেশে ক্যাশ অন ডেলিভারি")
        ->assertJsonPath('data.notes', 'Used the product facts and one reference page.')
        ->json('data');

    expect($done['settings'])->not->toHaveKey('live_viewers_text')
        ->and($done['settings'])->not->toHaveKey('atlas_pricing_now')
        ->and($done['settings']['review_items'])->toHaveCount(5)
        ->and(array_column($done['settings']['review_items'], 'stars'))->toBe([5, 4, 1, 5, 5])
        ->and($done['settings']['trust_items'])->toHaveCount(3)
        ->and(array_column($done['settings']['trust_items'], 'icon'))->toBe(['🚚', '💰', '🔒'])
        ->and(implode(' ', $done['warnings']))->toContain('Live viewers text');

    $job = LandingAiJob::where('uuid', $uuid)->firstOrFail();
    expect($job->input_tokens)->toBe(4200)
        ->and($job->output_tokens)->toBe(3100)
        ->and($job->personal_access_token_id)->toBe($token->id);
});

it('blocks reference links that point at private or local addresses', function () {
    $private = new ReferencePageFetcher(fn () => ['127.0.0.1']);
    $public = new ReferencePageFetcher(fn () => ['93.184.216.34']);

    expect($private->checkTarget('https://looks-public.example/page'))->toHaveKey('error')
        ->and($public->checkTarget('http://169.254.169.254/latest/meta-data'))->toHaveKey('error')
        ->and($public->checkTarget('http://10.0.0.5/'))->toHaveKey('error')
        ->and($public->checkTarget('http://[::1]/'))->toHaveKey('error')
        ->and($public->checkTarget('http://localhost:8080/'))->toHaveKey('error')
        ->and($public->checkTarget('ftp://files.example/x'))->toHaveKey('error')
        ->and($public->checkTarget('http://shop.example:22/'))->toHaveKey('error')
        ->and($public->checkTarget('https://user:pass@shop.example/'))->toHaveKey('error')
        ->and($public->checkTarget('https://shop.example/offer'))->toBe(['host' => 'shop.example', 'port' => 443, 'pin' => '93.184.216.34'])
        ->and(ReferencePageFetcher::isPublicIp('100.64.1.1'))->toBeFalse()
        ->and(ReferencePageFetcher::isPublicIp('::ffff:127.0.0.1'))->toBeFalse()
        ->and(ReferencePageFetcher::isPublicIp('8.8.8.8'))->toBeTrue();

    Http::fake([
        'shop.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin']),
    ]);
    $redirect = (new ReferencePageFetcher(fn (string $host) => $host === '127.0.0.1' ? ['127.0.0.1'] : ['93.184.216.34']))
        ->fetch('https://shop.example/offer');

    expect($redirect['ok'])->toBeFalse()
        ->and($redirect['error'])->toBe('Private addresses cannot be read');
    Http::assertSentCount(1);
});

it('allows one running page per store and enforces the daily limit', function () {
    landingAiPublicFetcher();
    Http::fake([
        'api.openai.com/v1/models' => Http::response(landingAiModelsList()),
        'api.openai.com/v1/responses/resp_active' => Http::response(['id' => 'resp_active', 'status' => 'in_progress']),
    ]);

    [$token, $plain] = landingAiStore();
    $active = LandingAiJob::create([
        'personal_access_token_id' => $token->id,
        'status' => LandingAiJob::STATUS_RUNNING,
        'openai_response_id' => 'resp_active',
        'spec' => landingAiSpec(),
    ]);

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['reference_urls' => [], 'images' => []]))
        ->assertStatus(409)
        ->assertJsonPath('data.job', $active->uuid)
        ->assertJsonPath('data.status', 'running');

    $active->update(['status' => LandingAiJob::STATUS_COMPLETED, 'finished_at' => now()]);
    app(LandingSettingsService::class)->update(['landing_ai_daily_limit' => 1]);

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['reference_urls' => [], 'images' => []]))
        ->assertStatus(429)
        ->assertJsonPath('message', "Today's limit of 1 AI pages is used. Please try again tomorrow.");

    Http::assertNotSent(fn (ClientRequest $request) => $request->method() === 'POST');
});

it('explains when the hub has no OpenAI key yet', function () {
    app(LandingSettingsService::class)->update(['openai_api_key' => null]);
    config(['landing.openai_api_key' => '']);
    Http::fake();

    [, $plain] = landingAiStore();

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload())
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI is not set up on the hub yet. Please contact WooEasyLife support.');

    expect(LandingAiJob::count())->toBe(0);
    Http::assertNothingSent();
});

it('fails with a safe message when OpenAI rejects the hub key', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(landingAiModelsList()),
        'api.openai.com/v1/responses' => Http::response([
            'error' => ['message' => 'Incorrect API key provided: sk-test-landing-ai-key.', 'code' => 'invalid_api_key'],
        ], 401),
    ]);

    [, $plain] = landingAiStore();

    $response = $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['reference_urls' => [], 'images' => []]))
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI is not available right now. Please contact WooEasyLife support.');

    expect($response->getContent())->not->toContain('sk-')
        ->and(LandingAiJob::first()->status)->toBe(LandingAiJob::STATUS_FAILED);
});

it('retries with the best model when the saved model is not on the account', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(landingAiModelsList()),
        'api.openai.com/v1/responses' => Http::sequence()
            ->push(['error' => ['message' => 'The model `gpt-4.1` does not exist.', 'code' => 'model_not_found']], 404)
            ->push(['id' => 'resp_retry', 'status' => 'queued']),
    ]);
    app(LandingSettingsService::class)->update(['openai_landing_model' => 'gpt-4.1']);

    [, $plain] = landingAiStore();

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['reference_urls' => [], 'images' => []]))
        ->assertStatus(202)
        ->assertJsonPath('data.model', 'gpt-5.1');

    $posts = Http::recorded(fn (ClientRequest $request) => $request->method() === 'POST')->values();
    expect($posts)->toHaveCount(2)
        ->and($posts[0][0]['model'])->toBe('gpt-4.1')
        ->and($posts[0][0]->data())->not->toHaveKey('reasoning')
        ->and($posts[1][0]['model'])->toBe('gpt-5.1');
});

it('does not show one store\'s job to another store', function () {
    Http::fake();
    [$tokenA] = landingAiStore('shop-a.example.com');
    [, $plainB] = landingAiStore('shop-b.example.com');

    $job = LandingAiJob::create([
        'personal_access_token_id' => $tokenA->id,
        'status' => LandingAiJob::STATUS_COMPLETED,
        'result' => ['settings' => ['product_name' => 'Secret'], 'notes' => ''],
    ]);

    $this->withHeaders(landingAiHeaders($plainB, 'shop-b.example.com'))
        ->getJson('/api/landing-ai/jobs/'.$job->uuid)
        ->assertStatus(404);
});

it('rejects bad input before any AI call', function () {
    Http::fake();
    [, $plain] = landingAiStore();

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['reference_urls' => ['not a url at all']]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Reference link 1 is not a valid web address.');

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['spec' => ['fields' => [['key' => 'Bad Key!']], 'lists' => []]]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'The widget sent no fields to write.');

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload(['images' => array_fill(0, 5, LANDING_AI_TINY_PNG)]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'Send at most 4 photos.');

    Http::assertNothingSent();
});

it('sends up to four picked photos to the model', function () {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(landingAiModelsList()),
        'api.openai.com/v1/responses' => Http::response(['id' => 'resp_photos', 'status' => 'queued']),
    ]);
    [, $plain] = landingAiStore();

    $this->withHeaders(landingAiHeaders($plain))
        ->postJson('/api/landing-ai/generate', landingAiPayload([
            'reference_urls' => [],
            'images' => array_fill(0, 4, LANDING_AI_TINY_PNG),
        ]))
        ->assertStatus(202);

    Http::assertSent(function (ClientRequest $request) {
        if ($request->url() !== 'https://api.openai.com/v1/responses' || $request->method() !== 'POST') {
            return false;
        }
        $content = $request['input'][0]['content'] ?? [];
        $photos = array_filter($content, fn ($part) => ($part['type'] ?? '') === 'input_image');

        return count($photos) === 4 && str_contains($content[0]['text'] ?? '', 'PHOTOS: 4 product photos are attached.');
    });
});
