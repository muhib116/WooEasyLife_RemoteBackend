<?php

use App\Services\FraudCheck\RedxFraudChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function redxStatsEnvelope(array $data, int $code = 200, bool $isError = false, string $message = ''): array
{
    return [
        'code' => $code,
        'isError' => $isError,
        'message' => $message,
        'data' => $data,
    ];
}

function configureRedxFraudCredentials(): void
{
    Cache::flush();
    config()->set('courier-checker.redx.phone', '01711111111');
    config()->set('courier-checker.redx.password', 'test-password');
    config()->set('fraud-checker-bd-courier.redx.phone', '01711111111');
    config()->set('fraud-checker-bd-courier.redx.password', 'test-password');
}

it('reads parcel counts when RedX returns the success envelope with HTTP 400', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response(redxStatsEnvelope([
            'successRate' => 60,
            'totalParcels' => '10',
            'deliveredParcels' => '6',
            'returnPercentage' => '4',
            'customerSegment' => 'regular',
        ]), 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01770989591');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(10)
        ->and($report['confirmed'])->toBe(6)
        ->and($report['cancel'])->toBe(4)
        ->and($report['success_rate'])->toBe('60%')
        ->and($report['customer_segment'])->toBe('regular');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.redx.com.bd/v4/auth/login'
            && $request['phone'] === '8801711111111';
    });
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'phoneNumber=8801770989591');
    });
});

it('still reads a normal HTTP 200 RedX stats response', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response([
            'data' => [
                'totalParcels' => 2,
                'deliveredParcels' => 2,
            ],
        ], 200),
    ]);

    $report = app(RedxFraudChecker::class)->check('01715017088');

    expect($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(2)
        ->and($report['confirmed'])->toBe(2)
        ->and($report['cancel'])->toBe(0)
        ->and($report['success_rate'])->toBe('100%');
});

it('treats a success envelope with zero parcels as empty history', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response(redxStatsEnvelope([
            'successRate' => 0,
            'totalParcels' => '0',
            'deliveredParcels' => '0',
            'returnPercentage' => '0',
        ]), 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['unavailable'] ?? false)->toBeFalse()
        ->and($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('No delivery history found on RedX.');
});

it('does not treat a real RedX error body as delivery history', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response([
            'code' => 400,
            'isError' => true,
            'message' => 'Phone number is invalid',
            'data' => [
                'totalParcels' => '10',
                'deliveredParcels' => '6',
            ],
        ], 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('RedX stats request failed: Phone number is invalid');
});

it('rejects a non-success HTTP 400 even when count fields are present', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response([
            'code' => 400,
            'isError' => false,
            'message' => '',
            'data' => [
                'totalParcels' => '3',
                'deliveredParcels' => '1',
            ],
        ], 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['confirmed'])->toBe(0);
});

it('reports login failure without calling the stats api', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'message' => 'Invalid credentials',
        ], 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['message'])->toBe('RedX login failed with configured credentials.');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'redx.com.bd/api/'));
});

it('refreshes the token after HTTP 401 and then reads the HTTP 400 success envelope', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::sequence()
            ->push(['isError' => true, 'message' => 'Unauthorized'], 401)
            ->push(redxStatsEnvelope([
                'totalParcels' => '8',
                'deliveredParcels' => '5',
            ]), 400),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['api_success'])->toBeTrue()
        ->and($report['total_order'])->toBe(8)
        ->and($report['confirmed'])->toBe(5)
        ->and($report['cancel'])->toBe(3);

    Http::assertSentCount(4);
});

it('does not surface an HTML block page as history', function () {
    configureRedxFraudCredentials();
    Http::preventStrayRequests();
    Http::fake([
        'https://api.redx.com.bd/v4/auth/login' => Http::response([
            'data' => ['accessToken' => 'redx-token'],
        ], 200),
        'https://redx.com.bd/*' => Http::response('<html>blocked</html>', 403, ['Content-Type' => 'text/html']),
    ]);

    $report = app(RedxFraudChecker::class)->check('01712345678');

    expect($report['unavailable'])->toBeTrue()
        ->and($report['total_order'])->toBe(0)
        ->and($report['message'])->toBe('RedX stats request failed (HTTP 403).')
        ->and($report['message'])->not->toContain('blocked');
});
