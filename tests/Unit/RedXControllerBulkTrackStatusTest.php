<?php

use App\Http\Controllers\Courier\RedXController;
use App\Models\CourierConfiguration;
use App\Services\Courier\CourierAccountService;
use App\Services\Courier\CourierShipmentService;
use App\Services\RedXCourierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

uses(Tests\TestCase::class);

it('defines resolveCatalogConfig for redx bulk status checks', function () {
    $ref = new ReflectionClass(RedXController::class);

    expect($ref->hasMethod('resolveCatalogConfig'))->toBeTrue();
});

it('returns bulk parcel statuses without calling an undefined method', function () {
    $redxService = Mockery::mock(RedXCourierService::class);
    $shipmentService = Mockery::mock(CourierShipmentService::class);
    $accountService = Mockery::mock(CourierAccountService::class);

    $config = new CourierConfiguration([
        'user_id' => 1,
        'slug' => 'redx',
        'secret_key' => 'token',
        'settings' => ['environment' => 'live'],
    ]);

    $redxService->shouldReceive('getAuthConfig')
        ->once()
        ->andReturn($config);

    $accountService->shouldReceive('environmentFromConfig')
        ->with($config)
        ->andReturn('live');

    $shipmentService->shouldReceive('groupConsignmentsByAccount')
        ->once()
        ->with('redx', ['RX-1'], 'live')
        ->andReturn([0 => ['RX-1']]);

    $accountService->shouldReceive('configurationForAccount')
        ->once()
        ->with(0, 1, 'redx')
        ->andReturn(null);

    $redxService->shouldReceive('getTrackingStatuses')
        ->once()
        ->with($config, ['RX-1'])
        ->andReturn(['RX-1' => 'dispatched']);

    Auth::shouldReceive('id')->andReturn(1);

    $controller = new RedXController($redxService, $shipmentService, $accountService);

    $request = Request::create('/api/redx/bulk-track-status', 'POST', [
        'consignment_ids' => ['RX-1'],
        'environment' => 'live',
    ]);

    $response = $controller->bulkTrackStatus($request);
    $payload = $response->getData(true);

    expect($payload['status'])->toBeTrue();
    expect($payload['data'])->toBe(['RX-1' => 'dispatched']);
});

it('returns a clear error when redx is not configured', function () {
    $redxService = Mockery::mock(RedXCourierService::class);
    $shipmentService = Mockery::mock(CourierShipmentService::class);
    $accountService = Mockery::mock(CourierAccountService::class);

    $redxService->shouldReceive('getAuthConfig')->andReturn(null);
    $shipmentService->shouldReceive('groupConsignmentsByAccount')
        ->once()
        ->with('redx', ['RX-1'], null)
        ->andReturn([0 => ['RX-1']]);
    $accountService->shouldReceive('configurationForAccount')
        ->once()
        ->with(0, 1, 'redx')
        ->andReturn(null);

    Auth::shouldReceive('id')->andReturn(1);

    $controller = new RedXController($redxService, $shipmentService, $accountService);

    $request = Request::create('/api/redx/bulk-track-status', 'POST', [
        'consignment_ids' => ['RX-1'],
        'environment' => 'live',
    ]);

    $response = $controller->bulkTrackStatus($request);
    $payload = $response->getData(true);

    expect($payload['status'])->toBeFalse();
    expect($payload['message'])->toContain('RedX settings');
});
