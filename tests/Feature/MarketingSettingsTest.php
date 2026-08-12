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
            ->has('settings.header_scripts')
            ->has('settings.header_scripts_source')
            ->has('settings.footer_scripts')
            ->has('settings.footer_scripts_source')
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

it('saves header and footer scripts from marketing settings', function () {
    $admin = createMarketingAdmin();
    $header = '<meta name="p:domain_verify" content="18497601a62b9cb9e1b1b32fb7d57ae2"/>';
    $footer = '<script>window.__welFooter=1</script>';

    $this->actingAs($admin)
        ->put(route('marketingSettings.update'), [
            'meta_pixel_id' => '',
            'header_scripts' => $header,
            'footer_scripts' => $footer,
        ])
        ->assertRedirect();

    $settings = app(LandingSettingsService::class);
    expect($settings->headerScripts())->toBe($header);
    expect($settings->footerScripts())->toBe($footer);

    $this->actingAs($admin)
        ->put(route('marketingSettings.update'), [
            'meta_pixel_id' => '',
            'header_scripts' => '',
            'footer_scripts' => '',
        ])
        ->assertRedirect();

    expect($settings->headerScripts())->toBeNull();
    expect($settings->footerScripts())->toBeNull();
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

it('injects header and footer scripts on public pages', function () {
    PlatformSetting::query()->updateOrCreate(
        ['key' => LandingSettingsService::HEADER_SCRIPTS_KEY],
        ['value' => '<meta name="p:domain_verify" content="18497601a62b9cb9e1b1b32fb7d57ae2"/>'],
    );
    PlatformSetting::query()->updateOrCreate(
        ['key' => LandingSettingsService::FOOTER_SCRIPTS_KEY],
        ['value' => '<script>window.__welFooter=1</script>'],
    );

    $this->get('/')
        ->assertOk()
        ->assertSee('name="p:domain_verify"', false)
        ->assertSee('18497601a62b9cb9e1b1b32fb7d57ae2', false)
        ->assertSee('window.__welFooter=1', false);
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
