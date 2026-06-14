<?php

use App\Services\FraudCheck\CourierReportFormatter;
use App\Services\FraudCheck\PaperflyFraudChecker;
use App\Services\FraudCheck\PathaoFraudChecker;
use App\Services\FraudCheck\SteadfastFraudChecker;
use App\Services\FraudCheckService;

it('returns the legacy fraud check response structure', function () {
    $steadfast = Mockery::mock(SteadfastFraudChecker::class);
    $steadfast->shouldReceive('check')->once()->with('01712345678')->andReturn([
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

    $service = new FraudCheckService($steadfast, $pathao, $paperfly);
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

it('rejects invalid phone numbers', function () {
    app(FraudCheckService::class)->getReport('12345');
})->throws(\InvalidArgumentException::class);

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

    $report = (new FraudCheckService($steadfast, $pathao, $paperfly))->getReport('01712345678');

    expect($report['success_rate'])->toBe('Good Customer');
});
