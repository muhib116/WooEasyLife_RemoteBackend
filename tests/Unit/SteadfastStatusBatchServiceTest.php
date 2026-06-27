<?php

use App\Models\CourierConfiguration;
use App\Services\Courier\SteadfastStatusBatchService;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

it('returns empty map when no ids are provided', function () {
    $config = new CourierConfiguration([
        'api_key' => 'test-key',
        'secret_key' => 'test-secret',
    ]);

    $service = new SteadfastStatusBatchService();

    expect($service->fetchStatuses($config, [], []))->toBe([]);
});

it('maps parallel steadfast status responses by consignment id', function () {
    Http::fake([
        'https://portal.packzy.com/api/v1/status_by_cid/1001' => Http::response([
            'status' => 200,
            'delivery_status' => 'delivered',
        ]),
        'https://portal.packzy.com/api/v1/status_by_cid/1002' => Http::response([
            'status' => 200,
            'delivery_status' => 'in_transit',
        ]),
    ]);

    $config = new CourierConfiguration([
        'api_key' => 'test-key',
        'secret_key' => 'test-secret',
    ]);

    $service = new SteadfastStatusBatchService();
    $statuses = $service->fetchStatuses($config, ['1001', '1002'], [], 2);

    expect($statuses)->toBe([
        '1001' => 'delivered',
        '1002' => 'in_transit',
    ]);
});
