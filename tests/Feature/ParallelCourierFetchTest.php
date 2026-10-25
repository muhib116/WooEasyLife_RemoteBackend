<?php

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use App\Services\FraudCheck\SteadfastFraudChecker;
use App\Services\FraudCheckService;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function parallelFetchService(PathaoFraudChecker $pathao): FraudCheckService
{
    config([
        'fraud_check.include_redx' => false,
        'fraud_check.include_carrybee' => false,
        'fraud_check.aggregate_redx' => false,
        'fraud_check.aggregate_carrybee' => false,
    ]);

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('poolPlan')->andReturn(null);
    $paperfly->shouldReceive('check')->andReturn([
        'total_order' => 0,
        'confirmed' => 0,
        'cancel' => 0,
        'success_rate' => 'No order history found!',
    ]);

    $redx = Mockery::mock(RedxFraudChecker::class);
    $carrybee = Mockery::mock(CarrybeeFraudChecker::class);
    $resolver = Mockery::mock(MerchantSteadfastFraudCredentialResolver::class);

    return new FraudCheckService(
        app(SteadfastFraudChecker::class),
        $pathao,
        $paperfly,
        $redx,
        $carrybee,
        $resolver,
    );
}

function fakeCourierLatency(int $delayMs): void
{
    Http::fake(function ($request) use ($delayMs) {
        usleep($delayMs * 1000);
        $url = $request->url();

        if (str_contains($url, 'portal.packzy.com')) {
            return Http::response([
                'status' => 200,
                'delivered_count' => 8,
                'cancelled_count' => 2,
                'delivery_ratio' => 80,
            ], 200);
        }

        if (str_contains($url, 'merchant.pathao.com')) {
            return Http::response([
                'data' => [
                    'successful_delivery' => 5,
                    'failed_delivery' => 1,
                    'total_delivery' => 6,
                ],
            ], 200);
        }

        return Http::response([], 500);
    });
}

it('checks saved-session couriers together and keeps the same totals', function () {
    $resolver = Mockery::mock(App\Services\FraudCheck\FraudPartnerCredentialResolver::class);
    $resolver->shouldReceive('candidates')->andReturn([[
        'id' => 1,
        'courier' => 'pathao',
        'identifier' => 'shop',
        'password' => 'secret',
        'label' => null,
        'source' => 'database',
    ]]);
    $resolver->shouldReceive('sessionCacheKey')->andReturn('pathao-pool-test');
    $resolver->shouldReceive('markSuccess')->andReturnNull();
    $resolver->shouldReceive('isConfigured')->andReturn(true);

    Cache::put('pathao-pool-test', 'cached-token', now()->addHour());

    $pathao = new PathaoFraudChecker($resolver);
    $service = parallelFetchService($pathao);
    $credentials = ['api_key' => 'test-key', 'secret_key' => 'test-secret'];

    config(['fraud_check.parallel_couriers' => true]);
    fakeCourierLatency(50);
    $parallel = $service->getReport('01712345678', $credentials);
    $parallelCalls = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();

    config(['fraud_check.parallel_couriers' => false]);
    fakeCourierLatency(50);
    $sequential = $service->getReport('01712345678', $credentials);

    expect($parallel['total_order'])->toBe(16)
        ->and($parallel['confirmed'])->toBe(13)
        ->and($parallel['cancel'])->toBe(3)
        ->and($parallel['courier'][0]['title'])->toBe('Stead Fast')
        ->and($parallel['courier'][1]['title'])->toBe('Pathao')
        ->and($sequential['total_order'])->toBe($parallel['total_order'])
        ->and($sequential['confirmed'])->toBe($parallel['confirmed'])
        ->and($sequential['cancel'])->toBe($parallel['cancel'])
        ->and($sequential['success_rate'])->toBe($parallel['success_rate'])
        ->and($parallelCalls)->toHaveCount(2)
        ->and(collect($parallelCalls)->contains(fn ($url) => str_contains($url, 'portal.packzy.com')))->toBeTrue()
        ->and(collect($parallelCalls)->contains(fn ($url) => str_contains($url, 'merchant.pathao.com')))->toBeTrue();
});

it('does not keep an empty pooled pathao response when the normal check has counts', function () {
    $resolver = Mockery::mock(App\Services\FraudCheck\FraudPartnerCredentialResolver::class);
    $resolver->shouldReceive('candidates')->andReturn([[
        'id' => 1,
        'courier' => 'pathao',
        'identifier' => 'shop',
        'password' => 'secret',
        'label' => null,
        'source' => 'database',
    ]]);
    $resolver->shouldReceive('sessionCacheKey')->andReturn('pathao-pool-test');
    $resolver->shouldReceive('markSuccess')->andReturnNull();
    $resolver->shouldReceive('isConfigured')->andReturn(true);
    $resolver->shouldReceive('markFailure')->andReturnNull();
    $resolver->shouldReceive('loginCandidates')->andReturn([]);
    $resolver->shouldReceive('forgetSessionCaches')->andReturn(0);

    Cache::put('pathao-pool-test', 'cached-token', now()->addHour());

    $pathaoCalls = 0;
    Http::fake(function ($request) use (&$pathaoCalls) {
        $url = $request->url();

        if (str_contains($url, 'portal.packzy.com')) {
            return Http::response([
                'status' => 200,
                'delivered_count' => 8,
                'cancelled_count' => 2,
                'delivery_ratio' => 80,
            ], 200);
        }

        if (str_contains($url, 'merchant.pathao.com')) {
            $pathaoCalls++;

            if ($pathaoCalls === 1) {
                return Http::response(['data' => ['is_new' => true]], 200);
            }

            return Http::response([
                'data' => [
                    'successful_delivery' => 5,
                    'failed_delivery' => 1,
                    'total_delivery' => 6,
                ],
            ], 200);
        }

        return Http::response([], 500);
    });

    config(['fraud_check.parallel_couriers' => true]);
    $report = parallelFetchService(new PathaoFraudChecker($resolver))
        ->getReport('01712345678', ['api_key' => 'test-key', 'secret_key' => 'test-secret']);

    expect($pathaoCalls)->toBe(2)
        ->and($report['total_order'])->toBe(16)
        ->and($report['confirmed'])->toBe(13)
        ->and($report['cancel'])->toBe(3);
});
