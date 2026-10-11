<?php

use App\Services\FraudCheck\CarrybeeFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function configureCarrybeeFraudCredentials(): void
{
    Cache::flush();
    config()->set('courier-checker.carrybee.phone', '01711111111');
    config()->set('courier-checker.carrybee.password', 'test-password');
    config()->set('fraud-checker-bd-courier.carrybee.phone', '01711111111');
    config()->set('fraud-checker-bd-courier.carrybee.password', 'test-password');
}

function fakeCarrybee(array $customerResponse, int $customerStatus = 200): void
{
    Http::fake([
        'https://merchant.carrybee.com/api/auth/csrf' => Http::response([
            'csrfToken' => 'csrf-token',
        ], 200),
        'https://merchant.carrybee.com/api/auth/callback/login*' => Http::response('server error', 500),
        'https://merchant.carrybee.com/api/auth/session' => Http::response([
            'accessToken' => 'carrybee-token',
            'user' => ['selectedBusinessId' => 'biz-1'],
        ], 200),
        'https://api-merchant.carrybee.com/*' => Http::response($customerResponse, $customerStatus),
    ]);
}

it('reads Carrybee delivery counts after a login callback that returns HTTP 500', function () {
    configureCarrybeeFraudCredentials();
    Http::preventStrayRequests();
    fakeCarrybee([
        'error' => false,
        'message' => 'Data fetched.',
        'data' => [
            'total_order' => 45,
            'cancelled_order' => 1,
            'success_rate' => 97,
            'fraud_count' => 0,
        ],
    ]);

    $report = app(CarrybeeFraudChecker::class)->check('01715017088');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(45)
        ->and($report['confirmed'])->toBe(44)
        ->and($report['cancel'])->toBe(1)
        ->and($report['success_rate'])->toBe('97%')
        ->and($report['frauds_count'])->toBe(0);
});

it('treats a missing Carrybee customer as empty history', function () {
    configureCarrybeeFraudCredentials();
    Http::preventStrayRequests();
    fakeCarrybee([
        'message' => 'Customer not found',
        'error' => true,
    ], 404);

    $report = app(CarrybeeFraudChecker::class)->check('01770989591');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('No delivery history found on Carrybee.');
});

it('keeps a real Carrybee lookup error unavailable', function () {
    configureCarrybeeFraudCredentials();
    Http::preventStrayRequests();
    fakeCarrybee([
        'error' => true,
        'message' => 'Business is suspended',
    ], 403);

    $report = app(CarrybeeFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('Carrybee customer lookup failed (HTTP 403).');
});
