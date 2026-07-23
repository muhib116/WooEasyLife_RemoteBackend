<?php

use App\Models\User;
use App\Services\LandingSettingsService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function createLandingSettingsAdmin(): User
{
    return User::create([
        'name' => 'Landing Settings Admin',
        'email' => 'landing-settings-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('includes openai settings on the landing settings page', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->get(route('landingSettings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('LandingSettings/Index')
            ->has('settings.openai_api_key')
            ->has('settings.openai_blog_model')
            ->has('settings.openai_blog_planning_model')
            ->has('settings.openai_blog_writing_model')
            ->has('settings.openai_image_model')
            ->has('settings.blog_ai_daily_token_cap')
            ->has('settings.blog_ai_daily_token_cap_source')
            ->has('settings.blog_model_options')
            ->has('settings.blog_planning_model_options')
            ->has('settings.blog_writing_model_options')
            ->has('settings.image_model_options')
        );
});

it('saves openai api key and model selections', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => 'sk-test-landing-settings-key',
            'openai_blog_model' => 'gpt-4o',
            'openai_blog_planning_model' => 'gpt-4.1-mini',
            'openai_blog_writing_model' => 'gpt-4.1',
            'openai_image_model' => 'dall-e-3',
        ])
        ->assertRedirect();

    $settings = app(LandingSettingsService::class);

    expect($settings->openaiApiKey())->toBe('sk-test-landing-settings-key')
        ->and($settings->openaiBlogModel())->toBe('gpt-4o')
        ->and($settings->openaiBlogPlanningModel())->toBe('gpt-4.1-mini')
        ->and($settings->openaiBlogWritingModel())->toBe('gpt-4.1')
        ->and($settings->openaiImageModel())->toBe('dall-e-3');
});

it('saves blog ai daily token cap override', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_blog_model' => 'gpt-4o-mini',
            'openai_image_model' => 'gpt-image-1',
            'blog_ai_daily_token_cap' => 850000,
        ])
        ->assertRedirect();

    $settings = app(LandingSettingsService::class);

    expect($settings->blogAiDailyTokenCap())->toBe(850000);
});

it('clears blog ai daily token cap when left blank and falls back to config', function () {
    $admin = createLandingSettingsAdmin();
    $settings = app(LandingSettingsService::class);

    $settings->update([
        'blog_ai_daily_token_cap' => 850000,
    ]);

    expect($settings->blogAiDailyTokenCap())->toBe(850000);

    config(['blog_ai.daily_token_cap' => 400000]);

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_blog_model' => 'gpt-4o-mini',
            'openai_image_model' => 'gpt-image-1',
            'blog_ai_daily_token_cap' => '',
        ])
        ->assertRedirect();

    expect($settings->blogAiDailyTokenCap())->toBe(400000);
});

it('rejects invalid blog ai daily token cap', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->from(route('landingSettings.index'))
        ->put(route('landingSettings.update'), [
            'blog_ai_daily_token_cap' => 50,
        ])
        ->assertRedirect(route('landingSettings.index'))
        ->assertSessionHasErrors(['blog_ai_daily_token_cap']);
});

it('rejects invalid openai model selections', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->from(route('landingSettings.index'))
        ->put(route('landingSettings.update'), [
            'openai_blog_model' => 'not-a-real-model',
            'openai_blog_planning_model' => 'fake-planning',
            'openai_blog_writing_model' => 'also-fake-writing',
            'openai_image_model' => 'also-fake',
        ])
        ->assertRedirect(route('landingSettings.index'))
        ->assertSessionHasErrors(['openai_blog_model', 'openai_blog_planning_model', 'openai_blog_writing_model', 'openai_image_model']);
});

it('clears openai api key when left blank', function () {
    $admin = createLandingSettingsAdmin();
    $settings = app(LandingSettingsService::class);

    $settings->update([
        'openai_api_key' => 'sk-temp-key',
        'openai_blog_model' => 'gpt-4o-mini',
        'openai_image_model' => 'gpt-image-1',
    ]);

    expect($settings->openaiApiKey())->toBe('sk-temp-key');

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => '',
            'openai_blog_model' => 'gpt-4o-mini',
            'openai_image_model' => 'gpt-image-1',
        ])
        ->assertRedirect();

    // DB override cleared; effective value may still come from .env / config.
    expect($settings->all()['openai_api_key_source'])->not->toBe('database');

    $envFallback = trim((string) config('landing.openai_api_key'));
    if ($envFallback === '') {
        expect($settings->openaiApiKey())->toBeNull();
    } else {
        expect($settings->openaiApiKey())->toBe($envFallback);
    }
});
