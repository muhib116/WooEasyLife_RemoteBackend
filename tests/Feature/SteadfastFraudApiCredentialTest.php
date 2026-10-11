<?php

use App\Models\User;
use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('uses steadfast api keys saved in the admin panel before env', function () {
    config([
        'fraud-checker-bd-courier.steadfast.api_key' => 'env-api-key',
        'fraud-checker-bd-courier.steadfast.secret_key' => 'env-secret-key',
    ]);

    app(SteadfastFraudApiCredentialStore::class)->put('admin-api-key', 'admin-secret-key');

    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 80,
            'cancellation_ratio' => 19,
            'delivered_count' => 8,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['api_success'] ?? false)->toBeTrue();
    expect($report['success_rate'])->toBe('80%');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/fraud_check/score/01712345678')
            && $request->hasHeader('Api-Key', 'admin-api-key')
            && $request->hasHeader('Secret-Key', 'admin-secret-key');
    });
});

it('falls back to the portal when the score api rejects the key', function () {
    config([
        'fraud-checker-bd-courier.steadfast.api_key' => 'bad-api-key',
        'fraud-checker-bd-courier.steadfast.secret_key' => 'bad-secret-key',
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
    ]);

    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response(['message' => 'Unauthenticated'], 401);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'GET') {
            return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'POST') {
            return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
        }

        if (str_contains($request->url(), '/user/frauds/check/')) {
            return Http::response([
                'delivery_ratio' => 78,
                'cancellation_ratio' => 21,
                'volume_band' => 'high',
                'volume_range' => '25+',
                'fraud_reports' => 0,
                'fraud_categories' => [],
                'frauds' => [],
                'delivered_count' => 26,
                'cancelled_count' => 7,
            ], 200);
        }

        return Http::response('', 404);
    });

    $report = app(SteadfastFraudChecker::class)->check('01752052358');

    expect($report['credential_error'] ?? false)->toBeFalse();
    expect($report['confirmed'])->toBe(26);
    expect($report['cancel'])->toBe(7);
    expect($report['success_rate'])->toBe('78%');
    expect($report['api_success'] ?? false)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/user/frauds/check/01752052358'));
});

it('keeps the saved steadfast secret when the admin form leaves it blank', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->put('admin-api-key', 'admin-secret-key');
    $store->put('replaced-api-key', null);

    expect($store->pair())->toBe([
        'api_key' => 'replaced-api-key',
        'secret_key' => 'admin-secret-key',
    ]);
});

it('shows the steadfast api page without the secret', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-steadfast-api@example.com',
        'phone' => '01700000999',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    app(SteadfastFraudApiCredentialStore::class)->put('visible-api-key', 'hidden-secret-key');

    $this->actingAs($admin)
        ->get(route('frauds.steadfastApi'))
        ->assertOk()
        ->assertSee('visible-api-key')
        ->assertDontSee('hidden-secret-key');
});
