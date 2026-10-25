<?php

use App\Models\FraudPartnerCredential;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use App\Services\FraudCheck\PathaoFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function configurePathaoFraudCredentials(): void
{
    Cache::flush();
    config()->set('fraud-checker-bd-courier.pathao.user', 'merchant@pathao.test');
    config()->set('fraud-checker-bd-courier.pathao.password', 'test-password');
}

function pathaoSuccessBody(): array
{
    return [
        'data' => [
            'total_delivery' => 11,
            'successful_delivery' => 7,
            'failed_delivery' => 4,
        ],
    ];
}

it('reuses the Pathao merchant token instead of logging in on every check', function () {
    configurePathaoFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response([
            'access_token' => 'pathao-merchant-token',
        ], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(pathaoSuccessBody(), 200),
    ]);

    $checker = app(PathaoFraudChecker::class);

    $first = $checker->check('01770989591');
    $second = $checker->check('01715017088');

    expect($first['total_order'])->toBe(11)
        ->and($first['confirmed'])->toBe(7)
        ->and($second['total_order'])->toBe(11);

    expect(pathaoLoginCount())->toBe(1);
});

it('logs in again only after Pathao rejects the cached merchant token', function () {
    configurePathaoFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response([
            'access_token' => 'pathao-merchant-token',
        ], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(pathaoSuccessBody(), 200)
            ->push(['message' => 'Unauthenticated'], 401)
            ->push(pathaoSuccessBody(), 200),
    ]);

    $checker = app(PathaoFraudChecker::class);

    expect($checker->check('01770989591')['total_order'])->toBe(11);
    expect($checker->check('01770989591')['total_order'])->toBe(11);

    expect(pathaoLoginCount())->toBe(2);
});

it('tries the next cached Pathao account when the first token gets a server error', function () {
    Cache::flush();
    silencePathaoOutsideMerchant();

    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'first@pathao.test',
        'secret' => 'first-password',
        'is_active' => true,
        'priority' => 1,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'second@pathao.test',
        'secret' => 'second-password',
        'is_active' => true,
        'priority' => 2,
    ]);

    $resolver = app(FraudPartnerCredentialResolver::class);
    Cache::put($resolver->sessionCacheKey('pathao', 'first@pathao.test'), 'token-first', now()->addMinutes(50));
    Cache::put($resolver->sessionCacheKey('pathao', 'second@pathao.test'), 'token-second', now()->addMinutes(50));

    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'should-not-login'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(['message' => 'unavailable'], 500)
            ->push(pathaoSuccessBody(), 200),
    ]);

    expect(app(PathaoFraudChecker::class)->check('01770989591')['total_order'])->toBe(11);
    expect(pathaoLoginCount())->toBe(0);
});

it('does not log in again when Pathao rate-limits the cached merchant token', function () {
    configurePathaoFraudCredentials();
    silencePathaoHermes();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response([
            'access_token' => 'pathao-merchant-token',
        ], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(pathaoSuccessBody(), 200)
            ->push(['message' => 'Too many requests'], 429),
    ]);

    $checker = app(PathaoFraudChecker::class);

    expect($checker->check('01770989591')['total_order'])->toBe(11);
    expect($checker->check('01770989591')['total_order'])->toBe(0);
    expect(pathaoLoginCount())->toBe(1);
});

it('logs in again when the cached Pathao token answers without customer data', function () {
    configurePathaoFraudCredentials();
    silencePathaoHermes();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response([
            'access_token' => 'fresh-pathao-token',
        ], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(['message' => 'user success rate', 'type' => 'success', 'code' => 200, 'data' => []], 200)
            ->push(pathaoSuccessBody(), 200),
    ]);

    $resolver = app(FraudPartnerCredentialResolver::class);
    Cache::put(
        $resolver->sessionCacheKey('pathao', 'merchant@pathao.test'),
        'stale-token',
        now()->addMinutes(50),
    );

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['total_order'])->toBe(11)
        ->and($report['confirmed'])->toBe(7)
        ->and($report['cancel'])->toBe(4)
        ->and(pathaoLoginCount())->toBe(1);
});

it('reads Pathao counts nested under the customer object', function () {
    configurePathaoFraudCredentials();
    silencePathaoHermes();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response([
            'access_token' => 'pathao-merchant-token',
        ], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response([
            'message' => 'user success rate',
            'type' => 'success',
            'code' => 200,
            'data' => [
                'show_count' => true,
                'customer_rating' => 'new_customer',
                'customer' => [
                    'total_delivery' => 3,
                    'successful_delivery' => 1,
                    'failed_delivery' => 2,
                ],
            ],
        ], 200),
    ]);

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['total_order'])->toBe(3)
        ->and($report['confirmed'])->toBe(1)
        ->and($report['cancel'])->toBe(2)
        ->and($report['unavailable'] ?? false)->toBeFalse();
});

it('logs in a different Pathao account after the cached one is forbidden', function () {
    Cache::flush();
    silencePathaoOutsideMerchant();

    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'blocked@pathao.test',
        'secret' => 'blocked-password',
        'is_active' => true,
        'priority' => 1,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'backup@pathao.test',
        'secret' => 'backup-password',
        'is_active' => true,
        'priority' => 2,
    ]);

    Cache::put(
        app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'blocked@pathao.test'),
        'token-blocked',
        now()->addMinutes(50),
    );

    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'token-backup'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(['message' => 'Forbidden'], 403)
            ->push(pathaoSuccessBody(), 200),
    ]);

    expect(app(PathaoFraudChecker::class)->check('01770989591')['total_order'])->toBe(11);
    expect(pathaoLoginCount())->toBe(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1/login')
        && $request['username'] === 'backup@pathao.test');
});

function silencePathaoHermes(): void
{
    config()->set('pathao-courier.pathao_client_id', null);
    config()->set('pathao-courier.pathao_client_secret', null);
    config()->set('pathao-courier.pathao_secret_token', null);
}

function silencePathaoOutsideMerchant(): void
{
    silencePathaoHermes();
    config()->set('fraud-checker-bd-courier.pathao.user', null);
    config()->set('fraud-checker-bd-courier.pathao.password', null);
}

function pathaoLoginCount(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/api/v1/login'))
        ->count();
}
