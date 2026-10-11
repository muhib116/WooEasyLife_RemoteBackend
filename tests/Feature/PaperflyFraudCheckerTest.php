<?php

use App\Services\FraudCheck\PaperflyFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function configurePaperflyFraudCredentials(): void
{
    Cache::flush();
    config()->set('fraud-checker-bd-courier.paperfly.user', 'merchant');
    config()->set('fraud-checker-bd-courier.paperfly.password', 'test-password');
    config()->set('courier-checker.paperfly.user', 'merchant');
    config()->set('courier-checker.paperfly.password', 'test-password');
}

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
