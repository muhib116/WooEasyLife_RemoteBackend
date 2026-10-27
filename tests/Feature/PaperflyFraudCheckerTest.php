<?php

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\CourierCallContext;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\FraudPartnerCredentialResolver;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use App\Services\FraudCheck\SteadfastFraudChecker;
use App\Services\FraudCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

function configurePaperflyFraudCredentials(): void
{
    Cache::flush();
    config()->set('fraud-checker-bd-courier.paperfly.user', 'merchant');
    config()->set('fraud-checker-bd-courier.paperfly.password', 'test-password');
    config()->set('courier-checker.paperfly.user', 'merchant');
    config()->set('courier-checker.paperfly.password', 'test-password');
}

function paperflySmartCheckCount(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), 'smart-check'))
        ->count();
}

function paperflyCountsBody(): array
{
    return [
        'delivered' => 4,
        'returned' => 1,
        'partial' => 0,
        'total' => 5,
        'smart_check' => ['delivery_rate' => 80],
    ];
}

function cachePaperflyToken(): void
{
    Cache::put(
        app(FraudPartnerCredentialResolver::class)->sessionCacheKey('paperfly', 'merchant'),
        'paperfly-token',
        now()->addMinutes(30),
    );
}

it('asks Paperfly once for a number when its pooled lookup gets a server error', function () {
    configurePaperflyFraudCredentials();
    cachePaperflyToken();
    config([
        'fraud_check.include_redx' => false,
        'fraud_check.include_carrybee' => false,
        'fraud_check.aggregate_redx' => false,
        'fraud_check.aggregate_carrybee' => false,
        'fraud_check.parallel_couriers' => true,
    ]);

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('poolPlan')->andReturn(null);
    $pathao->shouldReceive('check')->andReturn(CourierReportFormatter::emptyReport(['api_success' => true]));

    Http::preventStrayRequests();
    Http::fake([
        'https://portal.packzy.com/*' => Http::response([
            'status' => 200,
            'delivered_count' => 8,
            'cancelled_count' => 2,
            'delivery_ratio' => 80,
        ], 200),
        'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/*' => Http::response('<html>error</html>', 500),
    ]);

    $service = new FraudCheckService(
        app(SteadfastFraudChecker::class),
        $pathao,
        app(PaperflyFraudChecker::class),
        Mockery::mock(RedxFraudChecker::class),
        Mockery::mock(CarrybeeFraudChecker::class),
        Mockery::mock(MerchantSteadfastFraudCredentialResolver::class),
    );

    $report = $service->getReport('01712345678', ['api_key' => 'test-key', 'secret_key' => 'test-secret']);
    $paperfly = collect($report['courier'])->firstWhere('title', 'Paper Fly');

    expect(paperflySmartCheckCount())->toBe(1)
        ->and($paperfly['report']['unavailable'] ?? false)->toBeTrue()
        ->and($paperfly['report']['retry_later'] ?? false)->toBeTrue();
});

it('pauses Paperfly for five minutes after two server errors in a row', function () {
    configurePaperflyFraudCredentials();
    cachePaperflyToken();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/*' => Http::sequence()
            ->push('<html>error</html>', 500)
            ->push('<html>error</html>', 500)
            ->push(paperflyCountsBody(), 200)
            ->push(paperflyCountsBody(), 200),
    ]);

    $checker = app(PaperflyFraudChecker::class);

    $first = $checker->check('01712345678');
    expect($first['retry_later'])->toBeTrue()
        ->and($first['retry_after'])->toBe(60);

    $second = $checker->check('01712345679');
    expect($second['retry_later'])->toBeTrue()
        ->and($second['retry_after'])->toBe(300);

    // Paused: Paperfly is not asked at all.
    $paused = $checker->check('01712345670');
    expect($paused['deferred'])->toBeTrue()
        ->and($paused['retry_after'])->toBeGreaterThan(290)
        ->and($checker->paceStatus()['resting_seconds'])->toBeGreaterThan(290)
        ->and(paperflySmartCheckCount())->toBe(2);

    // After the pause one lookup checks whether Paperfly is back.
    $this->travel(301)->seconds();
    expect($checker->check('01712345670')['total_order'])->toBe(5)
        ->and($checker->check('01712345671')['total_order'])->toBe(5)
        ->and(paperflySmartCheckCount())->toBe(4);
});

it('spaces bulk Paperfly lookups four seconds apart without waiting in the request', function () {
    configurePaperflyFraudCredentials();
    cachePaperflyToken();
    Sleep::fake(syncWithCarbon: true);
    $this->freezeTime();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/*' => Http::response(paperflyCountsBody(), 200),
    ]);

    $checker = app(PaperflyFraudChecker::class);
    $bulk = fn (string $phone) => app(CourierCallContext::class)->asBulk(fn () => $checker->check($phone));

    expect($bulk('01712345678')['total_order'])->toBe(5);

    $busy = $bulk('01712345679');
    expect($busy['deferred'])->toBeTrue()
        ->and($busy['retry_after'])->toBe(4)
        ->and($checker->nextSlotSeconds())->toBe(0)
        ->and(paperflySmartCheckCount())->toBe(1);

    // A live check is not held back by the bulk gap.
    expect($checker->check('01712345670')['total_order'])->toBe(5);

    $this->travel(4)->seconds();
    expect($bulk('01712345679')['total_order'])->toBe(5)
        ->and(paperflySmartCheckCount())->toBe(3);
    Sleep::assertNeverSlept();
});

it('reads Paperfly smart-check v2 counts and keeps partial out of cancelled', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'token' => 'paperfly-token',
        ], 200),
        'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/smart-check-v2.php*' => Http::response([
            'customer_phone' => '01770989591',
            'delivered' => 23,
            'partial' => 0,
            'returned' => 1,
            'total' => 24,
            'smart_check' => [
                'delivery_rate' => 95.83,
                'return_rate' => 4.17,
            ],
        ], 200),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('8801770989591');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(24)
        ->and($report['confirmed'])->toBe(23)
        ->and($report['cancel'])->toBe(1)
        ->and($report['partial'])->toBe(0)
        ->and($report['success_rate'])->toBe('95.83%');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'search_text=01770989591'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'list.php'));
});

it('does not count a partial parcel as a cancel', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'token' => 'paperfly-token',
        ], 200),
        'https://go-app.paperfly.com.bd/*' => Http::response([
            'delivered' => 7,
            'partial' => 1,
            'returned' => 0,
            'total' => 7,
            'smart_check' => ['delivery_rate' => 100],
        ], 200),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('01715017088');

    expect($report['total_order'])->toBe(7)
        ->and($report['confirmed'])->toBe(7)
        ->and($report['cancel'])->toBe(0)
        ->and($report['partial'])->toBe(1)
        ->and($report['success_rate'])->toBe('100%');
});

it('treats a zero Paperfly smart-check as empty history', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'token' => 'paperfly-token',
        ], 200),
        'https://go-app.paperfly.com.bd/*' => Http::response([
            'delivered' => 0,
            'partial' => 0,
            'returned' => 0,
            'total' => 0,
        ], 200),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('01712345678');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('No delivery history found on Paperfly.');
});

it('does not treat a Paperfly error body as delivery history', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'token' => 'paperfly-token',
        ], 200),
        'https://go-app.paperfly.com.bd/*' => Http::response([
            'isError' => true,
            'message' => 'Smart check is unavailable',
            'delivered' => 10,
            'returned' => 2,
        ], 200),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('Paperfly smart-check failed: Smart check is unavailable');
});

it('reports Paperfly login failure without calling smart-check', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'message' => 'Invalid credentials',
        ], 400),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['message'])->toBe('Paperfly login failed with configured credentials.');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'smart-check'));
});

it('refreshes the Paperfly token after HTTP 401 and then reads smart-check v2', function () {
    configurePaperflyFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://go-app.paperfly.com.bd/merchant/api/react/authentication/login_using_password.php' => Http::response([
            'token' => 'paperfly-token',
        ], 200),
        'https://go-app.paperfly.com.bd/merchant/api/react/smart-check/*' => Http::sequence()
            ->push(['message' => 'Unauthorized'], 401)
            ->push([
                'delivered' => 4,
                'returned' => 1,
                'partial' => 0,
                'total' => 5,
                'smart_check' => ['delivery_rate' => 80],
            ], 200),
    ]);

    $report = app(PaperflyFraudChecker::class)->check('01712345678');

    expect($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(5)
        ->and($report['confirmed'])->toBe(4)
        ->and($report['cancel'])->toBe(1)
        ->and($report['success_rate'])->toBe('80%');
});
