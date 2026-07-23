<?php

use App\Http\Controllers\Admin\GoogleAnalyticsOAuthController;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Seo\GaCredentialStore;
use App\Services\Seo\GoogleAnalyticsClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function createGaOAuthAdmin(): User
{
    return User::create([
        'name' => 'GA OAuth Admin',
        'email' => 'ga-oauth-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

beforeEach(function () {
    config([
        'seo.ga.property_id' => '123456789',
        'seo.ga.client_id' => 'test-client-id',
        'seo.ga.client_secret' => 'test-client-secret',
        'seo.ga.refresh_token' => null,
        'seo.ga.access_token' => null,
        'seo.ga.oauth_redirect' => null,
    ]);

    PlatformSetting::query()
        ->where('key', GaCredentialStore::REFRESH_TOKEN_KEY)
        ->delete();
});

it('redirects platform admin to google oauth for ga connect', function () {
    $admin = createGaOAuthAdmin();

    $response = $this->actingAs($admin)
        ->get(route('maintenance.ga.connect'));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($location)->toContain('client_id=test-client-id')
        ->and($location)->toContain(urlencode('https://www.googleapis.com/auth/analytics.readonly'))
        ->and($location)->toContain('access_type=offline')
        ->and($location)->toContain('prompt=consent');

    $state = session(GoogleAnalyticsOAuthController::SESSION_STATE);
    expect($state)->not->toBeEmpty()
        ->and(cache(GoogleAnalyticsOAuthController::CACHE_PREFIX.$state))->toMatchArray([
            'user_id' => $admin->id,
        ]);
});

it('stores encrypted refresh token from oauth callback', function () {
    $admin = createGaOAuthAdmin();
    $state = 'test-oauth-state-abc';

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.access',
            'expires_in' => 3600,
            'refresh_token' => '1//refresh-from-google',
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $response = $this->actingAs($admin)
        ->withSession([GoogleAnalyticsOAuthController::SESSION_STATE => $state])
        ->get(route('maintenance.ga.callback', [
            'state' => $state,
            'code' => 'auth-code-123',
        ]));

    $response->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    $store = app(GaCredentialStore::class);
    expect($store->getRefreshToken())->toBe('1//refresh-from-google');

    $client = app(GoogleAnalyticsClient::class);
    expect($client->refreshToken())->toBe('1//refresh-from-google')
        ->and($client->refreshTokenSource())->toBe('database')
        ->and($client->configurationStatus()['has_refresh_token'])->toBeTrue()
        ->and($client->configurationStatus()['ready'])->toBeTrue();

    $row = PlatformSetting::query()->where('key', GaCredentialStore::REFRESH_TOKEN_KEY)->first();
    expect($row)->not->toBeNull();
    // Stored value must not be plaintext.
    expect($row->value)->not->toBe('1//refresh-from-google');
});

it('completes oauth callback from cache when session expired', function () {
    $admin = createGaOAuthAdmin();
    $state = 'cache-backed-state';

    cache()->put(GoogleAnalyticsOAuthController::CACHE_PREFIX.$state, [
        'user_id' => $admin->id,
    ], now()->addMinutes(20));

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.access',
            'expires_in' => 3600,
            'refresh_token' => '1//from-cache-flow',
            'token_type' => 'Bearer',
        ], 200),
    ]);

    $this->get(route('maintenance.ga.callback', [
        'state' => $state,
        'code' => 'auth-code-cache',
    ]))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($admin);
    expect(app(GaCredentialStore::class)->getRefreshToken())->toBe('1//from-cache-flow');
});

it('rejects oauth callback with invalid state', function () {
    $admin = createGaOAuthAdmin();

    $this->actingAs($admin)
        ->withSession([GoogleAnalyticsOAuthController::SESSION_STATE => 'expected'])
        ->get(route('maintenance.ga.callback', [
            'state' => 'wrong',
            'code' => 'auth-code',
        ]))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('error');
});

it('prefers database refresh token over env', function () {
    app(GaCredentialStore::class)->putRefreshToken('db-refresh');

    config(['seo.ga.refresh_token' => 'env-refresh']);

    $client = app(GoogleAnalyticsClient::class);

    expect($client->refreshToken())->toBe('db-refresh')
        ->and($client->refreshTokenSource())->toBe('database');
});

it('can disconnect and clear saved refresh token', function () {
    $admin = createGaOAuthAdmin();
    app(GaCredentialStore::class)->putRefreshToken('to-clear');

    $this->actingAs($admin)
        ->post(route('maintenance.ga.disconnect'))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    expect(app(GaCredentialStore::class)->getRefreshToken())->toBeNull()
        ->and(app(GoogleAnalyticsClient::class)->refreshTokenSource())->toBeNull();
});

it('includes connect_url in maintenance ga_status payload', function () {
    $admin = createGaOAuthAdmin();

    $this->actingAs($admin)
        ->getJson(route('maintenance.status'))
        ->assertOk()
        ->assertJsonPath('ga_status.can_connect', true)
        ->assertJsonPath('ga_status.connect_url', route('maintenance.ga.connect', absolute: false))
        ->assertJsonPath('ga_status.property_id_save_url', route('maintenance.ga.property', absolute: false));
});

it('saves ga property id from admin endpoint and prefers database over env', function () {
    $admin = createGaOAuthAdmin();
    config(['seo.ga.property_id' => '111111111']);

    $this->actingAs($admin)
        ->putJson(route('maintenance.ga.property'), ['property_id' => 'properties/987654321'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('ga_status.property_id', '987654321')
        ->assertJsonPath('ga_status.property_id_source', 'database');

    expect(app(\App\Services\Seo\GaCredentialStore::class)->getPropertyId())->toBe('987654321')
        ->and(app(\App\Services\Seo\GoogleAnalyticsClient::class)->propertyId())->toBe('987654321')
        ->and(app(\App\Services\Seo\GoogleAnalyticsClient::class)->propertyIdSource())->toBe('database');
});

it('returns ga realtime snapshot for dashboard viewers', function () {
    $admin = createGaOAuthAdmin();

    config([
        'seo.ga.property_id' => '123456789',
        'seo.ga.refresh_token' => 'refresh-xyz',
        'seo.ga.client_id' => 'test-client-id',
        'seo.ga.client_secret' => 'test-client-secret',
        'seo.ga.access_token' => null,
    ]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.access',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'analyticsdata.googleapis.com/v1beta/properties/*' => Http::sequence()
            ->push(['rows' => [['metricValues' => [['value' => '3']]]]], 200)
            ->push(['rows' => []], 200),
    ]);

    $this->actingAs($admin)
        ->getJson(route('siteVisitors.gaRealtime', ['force' => 1]))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('realtime.active_users', 3)
        ->assertJsonPath('realtime.ready', true);
});
