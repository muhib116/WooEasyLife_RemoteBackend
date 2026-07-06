<?php

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('resolves merchant steadfast portal credentials from courier configuration', function () {
    $user = User::create([
        'name' => 'Merchant',
        'email' => 'merchant@example.com',
        'phone' => '01700000000',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $user->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'api-key-123',
        'secret_key' => 'secret-key-456',
        'is_active' => true,
        'settings' => [
            'username' => 'merchant@steadfast.test',
            'password' => 'portal-password',
        ],
    ]);

    $credentials = app(MerchantSteadfastFraudCredentialResolver::class)->resolveForUserId($user->id);

    expect($credentials)->toBe([
        'username' => 'merchant@steadfast.test',
        'password' => 'portal-password',
    ]);
});

it('uses different session cache keys when merchant password changes', function () {
    $firstKey = SteadfastFraudChecker::sessionCacheKeyFor([
        'username' => 'merchant@steadfast.test',
        'password' => 'old-password',
    ]);

    $secondKey = SteadfastFraudChecker::sessionCacheKeyFor([
        'username' => 'merchant@steadfast.test',
        'password' => 'new-password',
    ]);

    expect($firstKey)->not->toBe($secondKey);
});

it('uses merchant steadfast credentials instead of env defaults', function () {
    config([
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
    ]);

    Cache::flush();

    Http::fake([
        'https://www.steadfast.com.bd/login' => Http::sequence()
            ->push('<input type="hidden" name="_token" value="csrf-token">', 200)
            ->push('', 302, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']),
        'https://www.steadfast.com.bd/user/frauds/check/*' => Http::response([
            'total_delivered' => 1,
            'total_cancelled' => 0,
            'frauds' => [],
        ], 200),
    ]);

    $checker = app(SteadfastFraudChecker::class);
    $checker->check('01712345678', [
        'username' => 'merchant@steadfast.test',
        'password' => 'merchant-portal-password',
    ]);

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || ! str_contains($request->url(), '/login')) {
            return false;
        }

        return ($request->data()['email'] ?? null) === 'merchant@steadfast.test'
            && ($request->data()['password'] ?? null) === 'merchant-portal-password';
    });
});

it('caches credential lookup on the request to avoid duplicate queries', function () {
    $user = User::create([
        'name' => 'Merchant',
        'email' => 'merchant-cache@example.com',
        'phone' => '01700000001',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $user->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'api-key-123',
        'secret_key' => 'secret-key-456',
        'is_active' => true,
        'settings' => [
            'username' => 'merchant@steadfast.test',
            'password' => 'portal-password',
        ],
    ]);

    $plainToken = 'test-token-' . bin2hex(random_bytes(16));

    AccessToken::unguarded(function () use ($user, $plainToken) {
        AccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Test Token',
            'token' => hash('sha256', $plainToken),
            'domain' => 'shop.example.com',
            'status' => true,
        ]);
    });

    $request = \Illuminate\Http\Request::create('/api/fraud-check', 'POST');
    $request->headers->set('Authorization', 'Bearer ' . $plainToken);

    $resolver = app(MerchantSteadfastFraudCredentialResolver::class);

    $queriesBefore = count(\Illuminate\Support\Facades\DB::getQueryLog());
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $first = $resolver->resolveFromRequest($request);
    $queriesAfterFirst = count(\Illuminate\Support\Facades\DB::getQueryLog());

    $second = $resolver->resolveFromRequest($request);
    $queriesAfterSecond = count(\Illuminate\Support\Facades\DB::getQueryLog());

    expect($first)->not->toBeNull();
    expect($second)->toBe($first);
    expect($queriesAfterFirst - $queriesBefore)->toBeGreaterThan(0);
    expect($queriesAfterSecond - $queriesAfterFirst)->toBe(0);
});
