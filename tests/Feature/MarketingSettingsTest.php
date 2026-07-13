<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\LandingSettingsService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function createMarketingAdmin(): User
{
    return User::create([
        'name' => 'Marketing Admin',
        'email' => 'marketing-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('requires auth for marketing settings', function () {
    $this->get(route('marketingSettings.index'))->assertRedirect();
});

it('shows marketing settings for platform admins', function () {
    $admin = createMarketingAdmin();

    $this->actingAs($admin)
        ->get(route('marketingSettings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MarketingSettings/Index')
            ->has('settings.meta_pixel_id')
            ->has('settings.meta_pixel_id_source')
        );
});

it('saves meta pixel id from marketing settings', function () {
    $admin = createMarketingAdmin();

    $this->actingAs($admin)
        ->put(route('marketingSettings.update'), [
            'meta_pixel_id' => '806373635894978',
        ])
        ->assertRedirect();

    expect(app(LandingSettingsService::class)->metaPixelId())->toBe('806373635894978');

    $this->actingAs($admin)
        ->put(route('marketingSettings.update'), [
            'meta_pixel_id' => '',
        ])
        ->assertRedirect();

    expect(app(LandingSettingsService::class)->metaPixelId())->toBeNull();
});

it('rejects invalid meta pixel ids', function () {
    $admin = createMarketingAdmin();

    $this->actingAs($admin)
        ->from(route('marketingSettings.index'))
        ->put(route('marketingSettings.update'), [
            'meta_pixel_id' => 'not-a-pixel',
        ])
        ->assertRedirect(route('marketingSettings.index'))
        ->assertSessionHasErrors('meta_pixel_id');
});

it('injects meta pixel on the landing page for guests', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => LandingSettingsService::META_PIXEL_ID_KEY],
        ['value' => '806373635894978'],
    );

    $this->get('/')
        ->assertOk()
        ->assertSee('connect.facebook.net/en_US/fbevents.js', false)
        ->assertSee('806373635894978', false)
        ->assertSee("fbq('track', 'PageView')", false);
});

it('also injects meta pixel for authenticated admins so testing works', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => LandingSettingsService::META_PIXEL_ID_KEY],
        ['value' => '806373635894978'],
    );

    $admin = createMarketingAdmin();

    $this->actingAs($admin)
        ->get('/')
        ->assertOk()
        ->assertSee('connect.facebook.net/en_US/fbevents.js', false)
        ->assertSee('806373635894978', false);
});
