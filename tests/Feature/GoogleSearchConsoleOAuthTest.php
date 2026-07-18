<?php

use App\Http\Controllers\Admin\GoogleSearchConsoleOAuthController;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Seo\GoogleSearchConsoleClient;
use App\Services\Seo\GscCredentialStore;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function createGscOAuthAdmin(): User
{
    return User::create([
        'name' => 'GSC OAuth Admin',
        'email' => 'gsc-oauth-'.uniqid().'@example.com',
        'phone' => '018'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

beforeEach(function () {
    config([
        'seo.gsc.site_url' => 'https://example.com/',
        'seo.gsc.client_id' => 'test-client-id',
        'seo.gsc.client_secret' => 'test-client-secret',
        'seo.gsc.refresh_token' => null,
        'seo.gsc.access_token' => null,
        'seo.gsc.oauth_redirect' => null,
    ]);

    PlatformSetting::query()
        ->where('key', GscCredentialStore::REFRESH_TOKEN_KEY)
        ->delete();
});

it('redirects platform admin to google oauth for gsc connect', function () {
    $admin = createGscOAuthAdmin();

    $response = $this->actingAs($admin)
        ->get(route('maintenance.gsc.connect'));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($location)->toContain('client_id=test-client-id')
        ->and($location)->toContain(urlencode('https://www.googleapis.com/auth/webmasters.readonly'))
        ->and($location)->toContain('access_type=offline')
        ->and($location)->toContain('prompt=consent');

    $state = session(GoogleSearchConsoleOAuthController::SESSION_STATE);
    expect($state)->not->toBeEmpty()
        ->and(cache(GoogleSearchConsoleOAuthController::CACHE_PREFIX.$state))->toMatchArray([
            'user_id' => $admin->id,
        ]);
});

it('stores encrypted refresh token from oauth callback', function () {
    $admin = createGscOAuthAdmin();
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
        ->withSession([GoogleSearchConsoleOAuthController::SESSION_STATE => $state])
        ->get(route('maintenance.gsc.callback', [
            'state' => $state,
            'code' => 'auth-code-123',
        ]));

    $response->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    $store = app(GscCredentialStore::class);
    expect($store->getRefreshToken())->toBe('1//refresh-from-google');

    $client = app(GoogleSearchConsoleClient::class);
    expect($client->refreshToken())->toBe('1//refresh-from-google')
        ->and($client->refreshTokenSource())->toBe('database')
        ->and($client->configurationStatus()['has_refresh_token'])->toBeTrue()
        ->and($client->configurationStatus()['ready'])->toBeTrue();

    $row = PlatformSetting::query()->where('key', GscCredentialStore::REFRESH_TOKEN_KEY)->first();
    expect($row)->not->toBeNull();
    // Stored value must not be plaintext.
    expect($row->value)->not->toBe('1//refresh-from-google');
});

it('completes oauth callback from cache when session expired', function () {
    $admin = createGscOAuthAdmin();
    $state = 'cache-backed-state';

    cache()->put(GoogleSearchConsoleOAuthController::CACHE_PREFIX.$state, [
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

    $this->get(route('maintenance.gsc.callback', [
        'state' => $state,
        'code' => 'auth-code-cache',
    ]))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    $this->assertAuthenticatedAs($admin);
    expect(app(GscCredentialStore::class)->getRefreshToken())->toBe('1//from-cache-flow');
});

it('rejects oauth callback with invalid state', function () {
    $admin = createGscOAuthAdmin();

    $this->actingAs($admin)
        ->withSession([GoogleSearchConsoleOAuthController::SESSION_STATE => 'expected'])
        ->get(route('maintenance.gsc.callback', [
            'state' => 'wrong',
            'code' => 'auth-code',
        ]))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('error');
});

it('prefers database refresh token over env', function () {
    app(GscCredentialStore::class)->putRefreshToken('db-refresh');

    config(['seo.gsc.refresh_token' => 'env-refresh']);

    $client = app(GoogleSearchConsoleClient::class);

    expect($client->refreshToken())->toBe('db-refresh')
        ->and($client->refreshTokenSource())->toBe('database');
});

it('can disconnect and clear saved refresh token', function () {
    $admin = createGscOAuthAdmin();
    app(GscCredentialStore::class)->putRefreshToken('to-clear');

    $this->actingAs($admin)
        ->post(route('maintenance.gsc.disconnect'))
        ->assertRedirect(route('maintenance.index'))
        ->assertSessionHas('success');

    expect(app(GscCredentialStore::class)->getRefreshToken())->toBeNull()
        ->and(app(GoogleSearchConsoleClient::class)->refreshTokenSource())->toBeNull();
});

it('includes connect_url in maintenance gsc_status payload', function () {
    $admin = createGscOAuthAdmin();

    $this->actingAs($admin)
        ->getJson(route('maintenance.status'))
        ->assertOk()
        ->assertJsonPath('gsc_status.can_connect', true)
        ->assertJsonPath('gsc_status.connect_url', route('maintenance.gsc.connect', absolute: false));
});
