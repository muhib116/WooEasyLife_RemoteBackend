<?php

use App\Services\FraudCheck\CarrybeeFraudChecker;
use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\MerchantSteadfastFraudCredentialResolver;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\RedxFraudChecker;
use App\Services\FraudCheck\SteadfastFraudChecker;
use App\Services\FraudCheckService;

function fraudCheckServiceWithMocks(
    $steadfast,
    $pathao,
    $paperfly,
    ?MerchantSteadfastFraudCredentialResolver $resolver = null,
    $redx = null,
    $carrybee = null,
    bool $includeRedx = false,
    bool $includeCarrybee = false,
): FraudCheckService {
    config([
        'fraud_check.include_redx' => $includeRedx,
        'fraud_check.include_carrybee' => $includeCarrybee,
        'fraud_check.aggregate_redx' => true,
    ]);

    $resolver ??= Mockery::mock(MerchantSteadfastFraudCredentialResolver::class);
    $resolver->shouldReceive('resolveFromCurrentRequest')->andReturn(null);

    $redx ??= Mockery::mock(RedxFraudChecker::class);
    $carrybee ??= Mockery::mock(CarrybeeFraudChecker::class);

    return new FraudCheckService($steadfast, $pathao, $paperfly, $redx, $carrybee, $resolver);
}

it('returns the legacy fraud check response structure', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->with('01712345678', null)->andReturn([
        'total_order' => 9,
        'confirmed' => 7,
        'cancel' => 2,
        'success_rate' => '78%',
        'frauds' => [['name' => 'Test User', 'details' => 'Sample fraud report']],
    ]);

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->with('01712345678')->andReturn([
        'total_order' => 10,
        'confirmed' => 8,
        'cancel' => 2,
        'success_rate' => '80%',
    ]);

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->with('01712345678')->andReturn([
        'total_order' => 4,
        'confirmed' => 3,
        'cancel' => 1,
        'success_rate' => '75%',
    ]);

    $service = fraudCheckServiceWithMocks($steadfast, $pathao, $paperfly);
    $report = $service->getReport('01712345678');

    expect($report)->toHaveKeys([
        'total_order',
        'confirmed',
        'frauds',
        'cancel',
        'success_rate',
        'courier',
    ]);
    expect($report['confirmed'])->toBe(18);
    expect($report['cancel'])->toBe(5);
    expect($report['total_order'])->toBe(23);
    expect($report['success_rate'])->toBe('79%');
    expect($report['courier'])->toHaveCount(3);
    expect($report['courier'][0]['title'])->toBe('Stead Fast');
    expect($report['courier'][0]['report']['frauds'])->toHaveCount(1);
});

it('includes redx in courier list and aggregate totals when enabled', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->andReturn([
        'total_order' => 10,
        'confirmed' => 8,
        'cancel' => 2,
        'success_rate' => '80%',
        'frauds' => [],
    ]);

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $redx = Mockery::mock(RedxFraudChecker::class);
    $redx->shouldReceive('check')->once()->with('01712345678')->andReturn([
        'total_order' => 5,
        'confirmed' => 4,
        'cancel' => 1,
        'success_rate' => '80%',
    ]);

    $carrybee = Mockery::mock(CarrybeeFraudChecker::class);
    $carrybee->shouldReceive('check')->once()->with('01712345678')->andReturn([
        'total_order' => 0,
        'confirmed' => 0,
        'cancel' => 0,
        'success_rate' => '2 fraud report(s)',
        'data_type' => 'fraud_reports',
        'frauds_count' => 2,
        'api_success' => true,
    ]);

    $report = fraudCheckServiceWithMocks(
        $steadfast,
        $pathao,
        $paperfly,
        null,
        $redx,
        $carrybee,
        includeRedx: true,
        includeCarrybee: true,
    )->getReport('01712345678');

    expect($report['courier'])->toHaveCount(5);
    expect(collect($report['courier'])->pluck('title')->all())->toBe([
        'Stead Fast',
        'Pathao',
        'Paper Fly',
        'RedX',
        'Carrybee',
    ]);
    // RedX counts are aggregated; Carrybee fraud-report counts are not.
    expect($report['total_order'])->toBe(15);
    expect($report['confirmed'])->toBe(12);
    expect($report['cancel'])->toBe(3);
    expect($report['success_rate'])->toBe('80%');
    expect($report['carrybee_frauds_count'])->toBe(2);
    expect($report['frauds'])->toHaveCount(1);
    expect($report['frauds'][0]['courier'])->toBe('carrybee');
    expect($report['courier'][4]['report']['status'])->toBe('ok');
});

it('does not use carrybee fraud-report text as overall success rate', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport(['frauds' => []]));

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $carrybee = Mockery::mock(CarrybeeFraudChecker::class);
    $carrybee->shouldReceive('check')->once()->andReturn([
        'total_order' => 0,
        'confirmed' => 0,
        'cancel' => 0,
        'success_rate' => '3 fraud report(s)',
        'data_type' => 'fraud_reports',
        'frauds_count' => 3,
        'api_success' => true,
    ]);

    $report = fraudCheckServiceWithMocks(
        $steadfast,
        $pathao,
        $paperfly,
        null,
        null,
        $carrybee,
        includeRedx: false,
        includeCarrybee: true,
    )->getReport('01712345678');

    expect($report['success_rate'])->toBe('No order history found!');
    expect($report['carrybee_frauds_count'])->toBe(3);
    expect($report['frauds'][0]['details'])->toContain('3 fraud report');
});

it('passes merchant steadfast credentials to the checker when resolved from request', function () {
    $merchantCredentials = [
        'username' => 'merchant@steadfast.test',
        'password' => 'portal-password',
    ];

    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')
        ->once()
        ->with('01712345678', $merchantCredentials)
        ->andReturn(CourierReportFormatter::emptyReport(['frauds' => []]));

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $resolver = Mockery::mock(MerchantSteadfastFraudCredentialResolver::class);
    $resolver->shouldReceive('resolveFromCurrentRequest')->once()->andReturn($merchantCredentials);

    $report = fraudCheckServiceWithMocks($steadfast, $pathao, $paperfly, $resolver)->getReport('01712345678');

    expect($report)->toHaveKey('courier');
});

it('rejects invalid phone numbers', function () {
    app(FraudCheckService::class)->getReport('12345');
})->throws(\InvalidArgumentException::class);

it('marks successful empty steadfast responses as ok instead of unavailable', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->with('01712345678', null)->andReturn([
        'total_order' => 0,
        'confirmed' => 0,
        'cancel' => 0,
        'success_rate' => 'No order history found!',
        'data_type' => 'delivery',
        'frauds' => [],
        'api_success' => true,
    ]);

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $report = fraudCheckServiceWithMocks($steadfast, $pathao, $paperfly)->getReport('01712345678');

    expect($report['courier'][0]['report']['status'])->toBe('ok');
    expect($report['courier'][0]['report']['message'])->toBe('No delivery history found on Steadfast.');
});

it('uses pathao customer rating when order counts are unavailable', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport(['frauds' => []]));

    $pathao = Mockery::mock(PathaoFraudChecker::class);
    $pathao->shouldReceive('check')->once()->andReturn([
        'total_order' => 0,
        'confirmed' => 0,
        'cancel' => 0,
        'success_rate' => 'Good Customer',
        'customer_rating' => 'good_customer',
        'data_type' => 'rating',
        'estimated_success_rate' => '85%',
    ]);

    $paperfly = Mockery::mock(PaperflyFraudChecker::class);
    $paperfly->shouldReceive('check')->once()->andReturn(CourierReportFormatter::emptyReport());

    $report = fraudCheckServiceWithMocks($steadfast, $pathao, $paperfly)->getReport('01712345678');

    expect($report['success_rate'])->toBe('Good Customer');
});
