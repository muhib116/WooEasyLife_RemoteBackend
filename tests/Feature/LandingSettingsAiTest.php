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
            ->has('settings.openai_image_model')
            ->has('settings.blog_model_options')
            ->has('settings.image_model_options')
        );
});

it('saves openai api key and model selections', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => 'sk-test-landing-settings-key',
            'openai_blog_model' => 'gpt-4o',
            'openai_image_model' => 'dall-e-3',
        ])
        ->assertRedirect();

    $settings = app(LandingSettingsService::class);

    expect($settings->openaiApiKey())->toBe('sk-test-landing-settings-key')
        ->and($settings->openaiBlogModel())->toBe('gpt-4o')
        ->and($settings->openaiImageModel())->toBe('dall-e-3');
});

it('rejects invalid openai model selections', function () {
    $admin = createLandingSettingsAdmin();

    $this->actingAs($admin)
        ->from(route('landingSettings.index'))
        ->put(route('landingSettings.update'), [
            'openai_blog_model' => 'not-a-real-model',
            'openai_image_model' => 'also-fake',
        ])
        ->assertRedirect(route('landingSettings.index'))
        ->assertSessionHasErrors(['openai_blog_model', 'openai_image_model']);
});

it('clears openai api key when left blank', function () {
    $admin = createLandingSettingsAdmin();
    $settings = app(LandingSettingsService::class);

    $settings->update([
        'openai_api_key' => 'sk-temp-key',
        'openai_blog_model' => 'gpt-4o-mini',
        'openai_image_model' => 'gpt-image-1',
    ]);

    $this->actingAs($admin)
        ->put(route('landingSettings.update'), [
            'openai_api_key' => '',
            'openai_blog_model' => 'gpt-4o-mini',
            'openai_image_model' => 'gpt-image-1',
        ])
        ->assertRedirect();

    expect($settings->openaiApiKey())->toBeNull();
});
