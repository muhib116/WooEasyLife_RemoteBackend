<?php

use App\Models\FraudPartnerCredential;
use App\Services\FraudCheck\CourierCallContext;
use App\Services\FraudCheck\CourierPace;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use App\Services\FraudCheck\PathaoFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

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

it('asks the next pathao account after a rate limit and rests the limited one', function () {
    twoCachedPathaoAccounts();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'should-not-login'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(['message' => 'Too many requests'], 429, ['Retry-After' => '30'])
            ->push(pathaoSuccessBody(), 200)
            ->push(pathaoSuccessBody(), 200),
    ]);

    $checker = app(PathaoFraudChecker::class);

    expect($checker->check('01576641352')['total_order'])->toBe(11)
        ->and(pathaoSuccessTokens())->toBe(['token-first', 'token-second'])
        ->and(pathaoLoginCount())->toBe(0);

    // The first account rests, so the next lookup goes to the second one.
    expect($checker->check('01576641352')['total_order'])->toBe(11)
        ->and(pathaoSuccessTokens())->toBe(['token-first', 'token-second', 'token-second'])
        ->and(pathaoLoginCount())->toBe(0);
});

it('does not ask another pathao account once the limit covers all of them', function () {
    twoCachedPathaoAccounts();
    Cache::put('courier_pace:pathao:shared', true, now()->addDay());
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'should-not-login'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(['message' => 'Too many requests'], 429),
    ]);

    $checker = app(PathaoFraudChecker::class);
    $report = $checker->check('01576641352');

    expect($report['total_order'])->toBe(0)
        ->and($report['rate_limited'])->toBeTrue()
        ->and($report['retry_after'])->toBe(65)
        ->and(pathaoSuccessCount())->toBe(1)
        ->and(pathaoLoginCount())->toBe(0);

    // Every account rests now: nothing is sent until the rest is over.
    $later = $checker->check('01576641352');

    expect($later['deferred'] ?? false)->toBeTrue()
        ->and(pathaoSuccessCount())->toBe(1)
        ->and($checker->nextSlotSeconds())->toBe(65);
});

it('rests pathao at least 65 seconds after a rate limit and asks again only after it', function () {
    configurePathaoFraudCredentials();
    silencePathaoHermes();
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();
    Cache::put(app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'merchant@pathao.test'), 'pathao-token', now()->addMinutes(50));
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push(['message' => 'Too many requests'], 429, ['Retry-After' => '10'])
            ->push(pathaoSuccessBody(), 200),
    ]);

    $checker = app(PathaoFraudChecker::class);
    $bulk = fn () => app(CourierCallContext::class)->asBulk(fn () => $checker->check('01576641352'));

    $limited = $bulk();
    expect($limited['rate_limited'])->toBeTrue()
        ->and($limited['retry_after'])->toBe(65)
        ->and($limited['message'])->toContain('slow down');

    $this->travel(60)->seconds();
    $early = $bulk();

    expect($early['deferred'])->toBeTrue()
        ->and($early['retry_after'])->toBe(5)
        ->and($early['message'])->toContain('not asked yet')
        ->and(pathaoSuccessCount())->toBe(1);

    $this->travel(6)->seconds();

    expect($bulk()['total_order'])->toBe(11)
        ->and(pathaoSuccessCount())->toBe(2)
        ->and(pathaoLoginCount())->toBe(0);
});

it('sends at most four bulk pathao lookups a minute per account and keeps one for a live check', function () {
    configurePathaoFraudCredentials();
    silencePathaoHermes();
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();
    Cache::put(app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'merchant@pathao.test'), 'pathao-token', now()->addMinutes(50));
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(pathaoSuccessBody(), 200),
    ]);

    $checker = app(PathaoFraudChecker::class);
    $bulk = fn () => app(CourierCallContext::class)->asBulk(fn () => $checker->check('01576641352'));

    expect($bulk()['total_order'])->toBe(11);

    $busy = $bulk();
    expect($busy['deferred'])->toBeTrue()
        ->and($busy['retry_after'])->toBe(16)
        ->and(pathaoSuccessCount())->toBe(1);

    foreach (range(1, 3) as $call) {
        $this->travel(16)->seconds();
        expect($bulk()['total_order'])->toBe(11);
    }

    expect(pathaoSuccessCount())->toBe(4);

    // A live order check right now still gets the fifth slot.
    expect($checker->check('01576641352')['total_order'])->toBe(11)
        ->and(pathaoSuccessCount())->toBe(5);

    // Pathao has had five lookups in this minute, so nothing else is sent.
    $full = $checker->check('01576641352');
    expect($full['deferred'])->toBeTrue()
        ->and(pathaoSuccessCount())->toBe(5)
        ->and(pathaoLoginCount())->toBe(0);
});

it('counts the hermes token as its own lane in the pathao pace', function () {
    configurePathaoFraudCredentials();
    config()->set('pathao-courier.pathao_client_id', 'hermes-client');
    config()->set('pathao-courier.pathao_client_secret', 'hermes-secret');
    config()->set('pathao-courier.pathao_secret_token', null);

    // The merchant account and the Hermes token: four bulk lookups a minute each.
    expect(app(PathaoFraudChecker::class)->paceStatus()['per_minute'])->toBe(7.7);

    silencePathaoHermes();

    expect(app(PathaoFraudChecker::class)->paceStatus()['per_minute'])->toBe(3.9);
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

it('tries the next cached Pathao account when the first account returns rating only', function () {
    Cache::flush();
    silencePathaoOutsideMerchant();

    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'rating-only@pathao.test',
        'secret' => 'first-password',
        'is_active' => true,
        'priority' => 1,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'counts@pathao.test',
        'secret' => 'second-password',
        'is_active' => true,
        'priority' => 2,
    ]);

    $resolver = app(FraudPartnerCredentialResolver::class);
    Cache::put($resolver->sessionCacheKey('pathao', 'rating-only@pathao.test'), 'token-rating', now()->addMinutes(50));
    Cache::put($resolver->sessionCacheKey('pathao', 'counts@pathao.test'), 'token-counts', now()->addMinutes(50));

    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/user/success' => Http::sequence()
            ->push([
                'data' => [
                    'show_count' => false,
                    'customer_rating' => 'good_customer',
                ],
            ], 200)
            ->push(pathaoSuccessBody(), 200),
    ]);

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['total_order'])->toBe(11)
        ->and($report['confirmed'])->toBe(7)
        ->and($report['cancel'])->toBe(4);
});

it('logs in the first Pathao account in list order and leaves the next one logged out', function () {
    twoPathaoAccountsWithoutSessions();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'token-early'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(pathaoSuccessBody(), 200),
    ]);

    expect(app(PathaoFraudChecker::class)->check('01770989591')['total_order'])->toBe(11)
        ->and(pathaoLoginCount())->toBe(1)
        ->and(pathaoLoginUsernames())->toBe(['early@pathao.test'])
        ->and(Cache::get(app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'later@pathao.test')))->toBeNull();
});

it('does not log in another Pathao account when the new login returns only a rating', function () {
    twoPathaoAccountsWithoutSessions();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'token-early'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response([
            'data' => [
                'show_count' => false,
                'customer_rating' => 'good_customer',
            ],
        ], 200),
    ]);

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['customer_rating'] ?? null)->not->toBeNull()
        ->and(pathaoLoginCount())->toBe(1)
        ->and(pathaoLoginUsernames())->toBe(['early@pathao.test']);
});

it('logs in the next Pathao account in list order when the earlier login fails', function () {
    twoPathaoAccountsWithoutSessions();
    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::sequence()
            ->push(['message' => 'rejected'], 422)
            ->push(['access_token' => 'token-later'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(pathaoSuccessBody(), 200),
    ]);

    expect(app(PathaoFraudChecker::class)->check('01770989591')['total_order'])->toBe(11)
        ->and(pathaoLoginCount())->toBe(2)
        ->and(pathaoLoginUsernames())->toBe(['early@pathao.test', 'later@pathao.test']);
});

it('does not log in when another worker takes the free Pathao lane', function () {
    Cache::flush();
    silencePathaoOutsideMerchant();

    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'busy@pathao.test',
        'secret' => 'busy-password',
        'is_active' => true,
        'priority' => 1,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'fresh@pathao.test',
        'secret' => 'fresh-password',
        'is_active' => true,
        'priority' => 2,
    ]);

    Cache::put(
        app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'busy@pathao.test'),
        'token-busy',
        now()->addMinutes(50),
    );

    app()->instance(CourierPace::class, new class(app(CourierCallContext::class)) extends CourierPace
    {
        public function waitSeconds(string $courier, string $lane): int
        {
            return 0;
        }

        public function claim(string $courier, string $lane, ?int $maxWaitSeconds = null): int
        {
            return 4;
        }
    });

    Http::preventStrayRequests();
    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'should-not-login'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(pathaoSuccessBody(), 200),
    ]);

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['deferred'])->toBeTrue()
        ->and($report['retry_after'])->toBe(4)
        ->and(pathaoLoginCount())->toBe(0)
        ->and(pathaoSuccessCount())->toBe(0);
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
    return count(pathaoLoginUsernames());
}

/**
 * @return list<string>
 */
function pathaoLoginUsernames(): array
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/api/v1/login'))
        ->map(fn ($pair) => (string) $pair[0]['username'])
        ->values()
        ->all();
}

function twoPathaoAccountsWithoutSessions(): void
{
    Cache::flush();
    silencePathaoOutsideMerchant();

    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'later@pathao.test',
        'secret' => 'later-password',
        'is_active' => true,
        'priority' => 2,
    ]);
    FraudPartnerCredential::query()->create([
        'courier' => 'pathao',
        'identifier' => 'early@pathao.test',
        'secret' => 'early-password',
        'is_active' => true,
        'priority' => 1,
    ]);
}

function pathaoSuccessCount(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/user/success'))
        ->count();
}

/**
 * @return list<string>
 */
function pathaoSuccessTokens(): array
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/user/success'))
        ->map(fn ($pair) => trim(str_replace('Bearer', '', $pair[0]->header('Authorization')[0] ?? '')))
        ->values()
        ->all();
}

function twoCachedPathaoAccounts(): void
{
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
}
