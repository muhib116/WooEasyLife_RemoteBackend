<?php

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\CourierLoginGuard;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

it('uses the token another worker saved instead of logging in again', function () {
    $guard = new CourierLoginGuard;
    $logins = 0;

    Cache::put('guard-test-token', 'fresh-token', now()->addMinutes(5));

    $token = $guard->login(
        'pathao',
        ['identifier' => 'shop', 'password' => 'secret'],
        fn () => Cache::get('guard-test-token'),
        function () use (&$logins) {
            $logins++;

            return 'new-token';
        },
    );

    expect($token)->toBe('fresh-token')
        ->and($logins)->toBe(0);
});

it('rests a courier account briefly after a login that could not reach the courier', function () {
    $guard = new CourierLoginGuard;
    $account = ['identifier' => 'shop', 'password' => 'secret'];

    expect(fn () => $guard->login('redx', $account, fn () => null, fn () => throw new RuntimeException('timed out')))
        ->toThrow(RuntimeException::class);

    expect($guard->resting('redx', $account))->toBeTrue();

    $this->travel(31)->seconds();

    expect($guard->resting('redx', $account))->toBeFalse();
});

it('does not hold a new courier password to the rest of the old one', function () {
    $guard = new CourierLoginGuard;

    $guard->restAfter('paperfly', ['identifier' => 'shop', 'password' => 'old-password'], 401);

    expect($guard->resting('paperfly', ['identifier' => 'shop', 'password' => 'old-password']))->toBeTrue()
        ->and($guard->resting('paperfly', ['identifier' => 'shop', 'password' => 'new-password']))->toBeFalse();
});

it('keeps a fresh courier token that another worker saved over the rejected one', function () {
    $guard = new CourierLoginGuard;

    Cache::put('guard-test-token', 'fresh-token', now()->addMinutes(5));
    $guard->forgetToken('guard-test-token', 'rejected-token');

    expect(Cache::get('guard-test-token'))->toBe('fresh-token');

    $guard->forgetToken('guard-test-token', 'fresh-token');

    expect(Cache::has('guard-test-token'))->toBeFalse();
});

it('does not try a rejected pathao password again on the next number', function () {
    config()->set('fraud-checker-bd-courier.pathao.user', 'merchant@pathao.test');
    config()->set('fraud-checker-bd-courier.pathao.password', 'wrong-password');

    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['message' => 'Invalid credentials'], 401),
        'https://merchant.pathao.com/api/v1/user/success' => Http::response(['data' => []], 200),
    ]);

    $checker = app(PathaoFraudChecker::class);
    $checker->check('01770989591');
    $checker->check('01715017088');

    expect(courierRequestCount('/api/v1/login'))->toBe(1);

    $this->travel(11)->minutes();
    $checker->check('01715017089');

    expect(courierRequestCount('/api/v1/login'))->toBe(2);
});

it('does not log pathao in again when only the connection dropped', function () {
    config()->set('fraud-checker-bd-courier.pathao.user', 'merchant@pathao.test');
    config()->set('fraud-checker-bd-courier.pathao.password', 'test-password');

    Cache::put(
        app(FraudPartnerCredentialResolver::class)->sessionCacheKey('pathao', 'merchant@pathao.test'),
        'good-token',
        now()->addMinutes(30),
    );

    Http::fake([
        'https://merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 'should-not-login'], 200),
        'https://merchant.pathao.com/api/v1/user/success' => Http::failedConnection('Connection reset by peer'),
    ]);

    $report = app(PathaoFraudChecker::class)->check('01770989591');

    expect($report['unavailable'] ?? false)->toBeTrue()
        ->and(courierRequestCount('/api/v1/login'))->toBe(0);
});

it('does not try a rejected paperfly password again on the next number', function () {
    config()->set('fraud-checker-bd-courier.paperfly.user', 'merchant');
    config()->set('fraud-checker-bd-courier.paperfly.password', 'wrong-password');
    config()->set('courier-checker.paperfly.user', 'merchant');
    config()->set('courier-checker.paperfly.password', 'wrong-password');

    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'message' => 'Invalid credentials',
        ], 400),
    ]);

    $checker = app(PaperflyFraudChecker::class);
    $first = $checker->check('01712345678');
    $checker->check('01712345679');

    expect($first['unavailable'])->toBeTrue()
        ->and(courierRequestCount('login_using_password'))->toBe(1);
});

it('does not try a rejected redx password again on the next number', function () {
    config()->set('courier-checker.redx.phone', '01711111111');
    config()->set('courier-checker.redx.password', 'wrong-password');
    config()->set('fraud-checker-bd-courier.redx.phone', '01711111111');
    config()->set('fraud-checker-bd-courier.redx.password', 'wrong-password');

    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response(['message' => 'Invalid phone or password'], 401),
    ]);

    $checker = app(RedxFraudChecker::class);
    $checker->check('01712345678');
    $checker->check('01712345679');

    expect(courierRequestCount('/v4/auth/login'))->toBe(1);
});

it('does not try a rejected carrybee password again on the next number', function () {
    config()->set('courier-checker.carrybee.phone', '01711111111');
    config()->set('courier-checker.carrybee.password', 'wrong-password');
    config()->set('fraud-checker-bd-courier.carrybee.phone', '01711111111');
    config()->set('fraud-checker-bd-courier.carrybee.password', 'wrong-password');

    Http::fake([
        'https://merchant.carrybee.com/api/auth/csrf' => Http::response(['csrfToken' => 'csrf-token'], 200),
        'https://merchant.carrybee.com/api/auth/callback/login*' => Http::response('Unauthorized', 401),
        'https://merchant.carrybee.com/api/auth/session' => Http::response([], 200),
    ]);

    $checker = app(CarrybeeFraudChecker::class);
    $checker->check('01712345678');
    $checker->check('01712345679');

    expect(courierRequestCount('/api/auth/csrf'))->toBe(1);
});

function courierRequestCount(string $needle): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), $needle))
        ->count();
}
