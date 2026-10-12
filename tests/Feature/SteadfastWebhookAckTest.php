<?php

use App\Http\Controllers\Admin\WebhookActivityController;
use App\Models\CourierAccount;
use App\Models\CourierForwardRetry;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Models\User;
use App\Services\Courier\CourierForwardRetryService;
use App\Services\Courier\CourierShipmentService;
use App\Services\Courier\CourierWebhookEventService;
use App\Services\Courier\SteadfastWebhookProcessor;
use App\Services\Courier\WordPressCourierForwarder;
use App\Services\OrderIntelligence\WebhookOrderIngestor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

function steadfastToken(): string
{
    return 'steadfast-webhook-token';
}

function seedSteadfastWebhook(string $consignmentId = '12345'): array
{
    $user = User::factory()->create();
    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-'.$user->id.'-'.$consignmentId),
        'webhook_verify_secret' => steadfastToken(),
        'is_active' => true,
    ]);

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => $consignmentId,
        'invoice' => 'INV-1',
        'wc_order_id' => 55,
        'user_id' => $user->id,
        'access_token_id' => 1,
        'site_url' => 'https://shop.example.com',
        'site_domain' => 'shop.example.com',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);

    return [$user, $account, $shipment];
}

function steadfastBody(string $consignmentId = '12345', string $status = 'delivered', string $updatedAt = '2025-03-02 12:45:30'): string
{
    return json_encode([
        'notification_type' => 'delivery_status',
        'consignment_id' => (int) $consignmentId,
        'invoice' => 'INV-1',
        'status' => $status,
        'updated_at' => $updatedAt,
    ], JSON_THROW_ON_ERROR);
}

function postSteadfast(string $body, string $token, ?string $signature = null, array $headers = [])
{
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    ];

    if ($signature !== null) {
        $server['HTTP_X_SIGNATURE'] = $signature;
    }

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', '/api/webhooks/steadfast', [], [], [], $server, $body);
}

function bindSteadfastScheduler(): object
{
    $bucket = (object) ['ids' => []];
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldNotReceive('forward');
    app()->instance(WordPressCourierForwarder::class, $forwarder);

    $processor = Mockery::mock(SteadfastWebhookProcessor::class, [
        app(CourierShipmentService::class),
        app(CourierWebhookEventService::class),
        $forwarder,
        app(CourierForwardRetryService::class),
        app(WebhookOrderIngestor::class),
    ])->makePartial();

    $processor->shouldReceive('schedule')->andReturnUsing(function (int $eventId) use ($bucket) {
        $bucket->ids[] = $eventId;
    });

    app()->instance(SteadfastWebhookProcessor::class, $processor);

    return $bucket;
}

it('returns 200 from the handler without calling the plugin forwarder', function () {
    seedSteadfastWebhook();
    $bucket = bindSteadfastScheduler();
    $body = steadfastBody();

    $response = postSteadfast($body, steadfastToken(), hash_hmac('sha256', $body, steadfastToken()));

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    expect($bucket->ids)->toHaveCount(1);
    expect(CourierWebhookEvent::query()->where('forward_status', 'queued')->count())->toBe(1);
});

it('accepts a valid raw-body hmac and bearer token', function () {
    seedSteadfastWebhook();
    bindSteadfastScheduler();
    $body = steadfastBody();

    $response = postSteadfast($body, steadfastToken(), hash_hmac('sha256', $body, steadfastToken()));

    $response->assertOk();
    expect(CourierWebhookEvent::query()->count())->toBe(1);
});

it('accepts a signature mismatch when enforce is off and logs it', function () {
    seedSteadfastWebhook();
    bindSteadfastScheduler();
    $logs = [];
    Log::listen(function ($event) use (&$logs) {
        $logs[] = ['message' => $event->message, 'context' => $event->context];
    });
    config(['courier.steadfast_enforce_webhook_signature' => false]);

    $response = postSteadfast(steadfastBody(), steadfastToken(), str_repeat('a', 64));

    $response->assertOk();
    expect(CourierWebhookEvent::query()->count())->toBe(1);
    $mismatch = collect($logs)->firstWhere('message', 'steadfast_signature_mismatch');
    expect($mismatch)->not->toBeNull();
    expect($mismatch['context']['signature_header_present'])->toBeTrue();
    expect(json_encode($mismatch['context']))->not->toContain(steadfastToken());
});

it('rejects a signature mismatch when enforce is on', function () {
    seedSteadfastWebhook();
    config(['courier.steadfast_enforce_webhook_signature' => true]);

    $response = postSteadfast(steadfastBody(), steadfastToken(), str_repeat('b', 64));

    $response->assertUnauthorized();
    expect(CourierWebhookEvent::query()->count())->toBe(0);
});

it('accepts bearer only and rejects an unknown bearer', function () {
    seedSteadfastWebhook();
    bindSteadfastScheduler();

    postSteadfast(steadfastBody(), steadfastToken())->assertOk();
    expect(CourierWebhookEvent::query()->count())->toBe(1);

    postSteadfast(steadfastBody('12345', 'delivered', '2025-03-02 13:00:00'), 'wrong-token')
        ->assertUnauthorized();
    expect(CourierWebhookEvent::query()->count())->toBe(1);
});

it('does not store an event when the consignment or status is empty', function () {
    seedSteadfastWebhook();
    bindSteadfastScheduler();

    postSteadfast('{"notification_type":"delivery_status","status":"delivered"}', steadfastToken())->assertOk();
    postSteadfast('{"notification_type":"delivery_status","consignment_id":12345,"status":""}', steadfastToken())->assertOk();

    expect(CourierWebhookEvent::query()->count())->toBe(0);
});

it('does not create a second event for an in-flight idempotency key', function () {
    seedSteadfastWebhook();
    $bucket = bindSteadfastScheduler();
    $body = steadfastBody();
    $headers = ['Idempotency-Key' => 'evt-1'];

    postSteadfast($body, steadfastToken(), null, $headers)->assertOk();
    postSteadfast($body, steadfastToken(), null, $headers)->assertOk();

    expect(CourierWebhookEvent::query()->count())->toBe(1);
    expect($bucket->ids)->toHaveCount(1);
});

it('requeues a failed event when the same idempotency key is retried', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $bucket = bindSteadfastScheduler();
    $event = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'idempotency_key' => 'evt-failed',
        'forward_status' => 'failed',
        'payload_summary' => [
            'updated_at' => '2025-03-02 12:45:30',
            'forward_payload' => ['raw_status' => 'delivered', 'updated_at' => '2025-03-02 12:45:30'],
        ],
    ]);

    postSteadfast(steadfastBody(), steadfastToken(), null, ['Idempotency-Key' => 'evt-failed'])->assertOk();

    expect($event->fresh()->forward_status)->toBe('queued');
    expect(CourierWebhookEvent::query()->count())->toBe(1);
    expect($bucket->ids)->toBe([$event->id]);
});

it('returns 200 when a parallel insert hits the idempotency unique key', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldNotReceive('forward');
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $events = Mockery::mock(CourierWebhookEventService::class);
    $events->shouldReceive('record')->once()->andReturnUsing(function () use ($shipment) {
        CourierWebhookEvent::query()->create([
            'partner' => 'steadfast',
            'environment' => 'live',
            'consignment_id' => '12345',
            'shipment_id' => $shipment->id,
            'access_token_id' => $shipment->access_token_id,
            'site_url' => $shipment->site_url,
            'wc_order_id' => $shipment->wc_order_id,
            'event_type' => 'delivered',
            'idempotency_key' => 'evt-race',
            'forward_status' => 'queued',
            'payload_summary' => [
                'updated_at' => '2025-03-02 12:45:30',
                'forward_payload' => ['raw_status' => 'delivered', 'updated_at' => '2025-03-02 12:45:30'],
            ],
        ]);

        throw new UniqueConstraintViolationException(
            'mysql',
            'insert into `courier_webhook_events` (`idempotency_key`) values (?)',
            ['evt-race'],
            new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry')
        );
    });
    $this->app->instance(CourierWebhookEventService::class, $events);

    postSteadfast(steadfastBody(), steadfastToken(), null, ['Idempotency-Key' => 'evt-race'])->assertOk();

    expect(CourierWebhookEvent::query()->count())->toBe(1);
    expect(CourierWebhookEvent::query()->first()->forward_status)->toBe('queued');
});

it('forwards to the plugin with an 8 second timeout', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->once()
        ->with(Mockery::type(CourierShipment::class), Mockery::type('array'), SteadfastWebhookProcessor::FORWARD_TIMEOUT_SECONDS)
        ->andReturn(['success' => true, 'message' => 'forwarded']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $event = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'forward_status' => 'queued',
        'payload_summary' => [
            'updated_at' => '2025-03-02 12:45:30',
            'forward_payload' => [
                'partner' => 'steadfast',
                'raw_status' => 'delivered',
                'updated_at' => '2025-03-02 12:45:30',
                'order_id' => 55,
                'courier_account_id' => $shipment->courier_account_id,
            ],
        ],
    ]);

    app(SteadfastWebhookProcessor::class)->process($event->id);

    expect($event->fresh()->forward_status)->toBe('success');
    expect($shipment->fresh()->status)->toBe('delivered');
});

it('skips the plugin post when a newer success already exists', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldNotReceive('forward');
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'forward_status' => 'success',
        'forward_message' => 'forwarded',
        'payload_summary' => ['updated_at' => '2025-03-02 13:00:00'],
    ]);

    $older = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'pending',
        'forward_status' => 'queued',
        'payload_summary' => [
            'updated_at' => '2025-03-02 12:00:00',
            'forward_payload' => ['raw_status' => 'pending', 'updated_at' => '2025-03-02 12:00:00'],
        ],
    ]);

    app(SteadfastWebhookProcessor::class)->process($older->id);

    expect($older->fresh()->forward_status)->toBe('success');
    expect($older->fresh()->forward_message)->toBe('stale_update_ignored');
});

it('keeps a 200 and queues a retry when the plugin post fails', function () {
    seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->once()
        ->andReturn(['success' => false, 'message' => 'forward_failed']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    postSteadfast(steadfastBody(), steadfastToken())->assertOk();

    $event = CourierWebhookEvent::query()->first();
    expect($event->forward_status)->toBe('retry_queued');
    expect(CourierForwardRetry::query()->where('status', 'pending')->count())->toBe(1);
});

it('replaces an older pending retry payload with a newer status', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $service = app(CourierForwardRetryService::class);
    $older = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'event_type' => 'pending',
        'forward_status' => 'retry_queued',
    ]);
    $newer = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'event_type' => 'delivered',
        'forward_status' => 'retry_queued',
    ]);

    $service->queueRetry($shipment, [
        'raw_status' => 'pending',
        'updated_at' => '2025-03-02 12:00:00',
    ], $older, 'forward_failed');
    $service->queueRetry($shipment, [
        'raw_status' => 'delivered',
        'updated_at' => '2025-03-02 13:00:00',
    ], $newer, 'forward_failed');
    $service->queueRetry($shipment, [
        'raw_status' => 'in_review',
        'updated_at' => '2025-03-02 12:30:00',
    ], $older, 'forward_failed');

    expect(CourierForwardRetry::query()->count())->toBe(1);
    $retry = CourierForwardRetry::query()->first();
    expect($retry->payload['raw_status'])->toBe('delivered');
    expect($retry->webhook_event_id)->toBe($newer->id);
});

it('claims queued steadfast events older than 20 seconds and resets stuck forwarding', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->twice()
        ->andReturn(['success' => true, 'message' => 'forwarded']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $payload = [
        'updated_at' => '2025-03-02 12:45:30',
        'forward_payload' => [
            'raw_status' => 'delivered',
            'updated_at' => '2025-03-02 12:45:30',
            'order_id' => 55,
            'courier_account_id' => $shipment->courier_account_id,
        ],
    ];

    $oldQueued = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'forward_status' => 'queued',
        'payload_summary' => $payload,
    ]);
    $oldQueued->forceFill([
        'created_at' => now()->subSeconds(SteadfastWebhookProcessor::QUEUED_CLAIM_AFTER_SECONDS + 5),
        'updated_at' => now()->subSeconds(SteadfastWebhookProcessor::QUEUED_CLAIM_AFTER_SECONDS + 5),
    ])->save();

    $young = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'pending',
        'forward_status' => 'queued',
        'payload_summary' => [
            'updated_at' => '2025-03-02 12:00:00',
            'forward_payload' => ['raw_status' => 'pending', 'updated_at' => '2025-03-02 12:00:00'],
        ],
    ]);

    $stuck = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'in_review',
        'forward_status' => 'forwarding',
        'payload_summary' => [
            'updated_at' => '2025-03-02 14:00:00',
            'forward_payload' => ['raw_status' => 'in_review', 'updated_at' => '2025-03-02 14:00:00', 'order_id' => 55],
        ],
    ]);
    $stuck->forceFill([
        'created_at' => now()->subSeconds(SteadfastWebhookProcessor::FORWARDING_STUCK_AFTER_SECONDS + 5),
        'updated_at' => now()->subSeconds(SteadfastWebhookProcessor::FORWARDING_STUCK_AFTER_SECONDS + 5),
    ])->save();

    $this->artisan('courier:retry-webhook-forwards')->assertSuccessful();

    expect($oldQueued->fresh()->forward_status)->toBe('success');
    expect($stuck->fresh()->forward_status)->toBe('success');
    expect($young->fresh()->forward_status)->toBe('queued');
});

it('process waiting claims queued steadfast events from the activities page', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->once()
        ->withArgs(function ($ship, array $payload) {
            return ($payload['raw_status'] ?? null) === 'delivered'
                && ! array_key_exists('consignment_id', $payload);
        })
        ->andReturn(['success' => true, 'message' => 'forwarded']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $event = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'forward_status' => 'queued',
        'payload_summary' => [
            'consignment_id' => '12345',
            'updated_at' => '2025-03-02 12:45:30',
            'forward_payload' => [
                'raw_status' => 'delivered',
                'updated_at' => '2025-03-02 12:45:30',
                'order_id' => 55,
                'courier_account_id' => $shipment->courier_account_id,
            ],
        ],
    ]);
    $event->forceFill([
        'created_at' => now()->subSeconds(SteadfastWebhookProcessor::QUEUED_CLAIM_AFTER_SECONDS + 5),
        'updated_at' => now()->subSeconds(SteadfastWebhookProcessor::QUEUED_CLAIM_AFTER_SECONDS + 5),
    ])->save();

    $response = app(WebhookActivityController::class)
        ->processRetries(Request::create('/webhooks/process-retries', 'POST'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true)['recovered']['processed'])->toBe(1);
    expect($event->fresh()->forward_status)->toBe('success');
});

it('retries an event with the stored forward payload and omits consignment id', function () {
    [, , $shipment] = seedSteadfastWebhook();
    $captured = null;
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->once()
        ->withArgs(function ($ship, array $payload) use (&$captured) {
            $captured = $payload;

            return true;
        })
        ->andReturn(['success' => true, 'message' => 'forwarded']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $event = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '12345',
        'shipment_id' => $shipment->id,
        'wc_order_id' => 55,
        'event_type' => 'delivered',
        'forward_status' => 'failed',
        'payload_summary' => [
            'partner' => 'steadfast',
            'consignment_id' => '12345',
            'updated_at' => '2025-03-02 12:45:30',
            'forward_payload' => [
                'partner' => 'steadfast',
                'raw_status' => 'delivered',
                'updated_at' => '2025-03-02 12:45:30',
                'tracking_message' => 'left at gate',
                'order_id' => 55,
                'courier_account_id' => $shipment->courier_account_id,
            ],
        ],
    ]);

    $result = app(CourierForwardRetryService::class)->retryFromEvent($event);

    expect($result['success'])->toBeTrue();
    expect($captured)->not->toHaveKey('consignment_id');
    expect($captured)->not->toHaveKey('forward_payload');
    expect($captured['raw_status'])->toBe('delivered');
    expect($captured['tracking_message'])->toBe('left at gate');
    expect($captured['order_id'])->toBe(55);
    expect($event->fresh()->forward_status)->toBe('success');
});

it('forwards an orphan after the shipment mapping appears', function () {
    [, , $shipment] = seedSteadfastWebhook('99901');
    $captured = null;
    $forwarder = Mockery::mock(WordPressCourierForwarder::class);
    $forwarder->shouldReceive('forward')
        ->once()
        ->withArgs(function ($ship, array $payload) use (&$captured) {
            $captured = $payload;

            return ($payload['raw_status'] ?? null) === 'delivered'
                && ! array_key_exists('consignment_id', $payload);
        })
        ->andReturn(['success' => true, 'message' => 'forwarded']);
    $this->app->instance(WordPressCourierForwarder::class, $forwarder);

    $event = CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '99901',
        'shipment_id' => null,
        'wc_order_id' => null,
        'event_type' => 'delivered',
        'forward_status' => 'orphan',
        'forward_message' => 'mapping_not_found',
        'payload_summary' => [
            'updated_at' => '2025-03-02 12:45:30',
            'forward_payload' => [
                'partner' => 'steadfast',
                'raw_status' => 'delivered',
                'updated_at' => '2025-03-02 12:45:30',
                'invoice' => 'INV-1',
                'consignment_id' => '99901',
            ],
        ],
    ]);
    $event->forceFill([
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ])->save();

    $this->artisan('courier:retry-webhook-forwards')->assertSuccessful();

    expect($event->fresh()->forward_status)->toBe('success');
    expect($event->fresh()->wc_order_id)->toBe(55);
    expect($captured['order_id'])->toBe(55);
    expect($shipment->fresh()->status)->toBe('delivered');
});

it('counts queued and orphan events for the activities page', function () {
    CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '1',
        'event_type' => 'delivered',
        'forward_status' => 'queued',
    ]);
    CourierWebhookEvent::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '2',
        'event_type' => 'delivered',
        'forward_status' => 'orphan',
    ]);

    $response = app(WebhookActivityController::class)->events(Request::create('/webhooks/events', 'GET'));
    $stats = $response->getData(true)['stats'];

    expect($stats['total_events'])->toBe(2);
    expect($stats['queued_count'])->toBe(1);
    expect($stats['orphan_count'])->toBe(1);
    expect($stats['success_count'])->toBe(0);
});
