<?php

/**
 * Full Order Intelligence lifecycle — each test is one step in the pipeline.
 * Run individually: php artisan test --filter="lifecycle step"
 */

use App\Domain\OrderIntelligence\OrderStatus;
use App\Models\AccessToken;
use App\Models\CourierShipment;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\CourierEntryIngestor;
use App\Services\OrderIntelligence\FraudCheckIngestor;
use App\Services\OrderIntelligence\FraudCheckOrderContext;
use App\Services\OrderIntelligence\StatsProjector;
use App\Services\OrderIntelligence\WebhookOrderIngestor;
use Illuminate\Support\Facades\Hash;

function lifecycleMerchant(): array
{
    $user = User::create([
        'name' => 'Lifecycle Merchant',
        'email' => 'lifecycle-' . uniqid() . '@example.com',
        'phone' => '017' . random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    $plainToken = 'lifecycle-token-' . bin2hex(random_bytes(8));
    $domain = 'lifecycle-shop.example.com';

    AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
        AccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Lifecycle Token',
            'token' => hash('sha256', $plainToken),
            'domain' => $domain,
            'status' => true,
        ]);
    });

    UserPackage::create([
        'title' => 'Lifecycle Plan',
        'domain' => $domain,
        'user_id' => $user->id,
        'package_hub_id' => 1,
        'total_order_can_handle' => 1000,
        'remaining_order' => 900,
        'total_order_handled' => 100,
        'per_order_rate' => 1,
        'total_cost' => 100,
        'transaction_charge' => 0,
        'is_active' => true,
    ]);

    return [$user, $plainToken, $domain];
}

function lifecycleHeaders(string $plainToken, string $domain): array
{
    return [
        'Authorization' => 'Bearer ' . $plainToken,
        'Origin' => 'https://' . $domain,
    ];
}

function seedLifecycleOrder(): array
{
    [$user, $plainToken, $domain] = lifecycleMerchant();
    $token = AccessToken::query()->where('domain', $domain)->firstOrFail();
    $phone = '01711223344';

    app(FraudCheckIngestor::class)->ingest(
        new FraudCheckOrderContext(
            accessTokenId: $token->id,
            userId: $user->id,
            phone: $phone,
            wcOrderId: 99001,
            name: 'Lifecycle Customer',
            address: 'Dhaka',
            productTitle: 'Test Product',
            orderAmount: 1500,
        ),
        ['total_order' => 5, 'confirmed' => 4, 'cancel' => 1, 'success_rate' => '80%', 'frauds' => []],
        ['total_order' => 3, 'confirmed' => 2, 'cancel' => 1, 'success_rate' => '67%'],
        ['total_order' => 0, 'confirmed' => 0, 'cancel' => 0],
    );

    $customerId = PlatformCustomer::query()->where('phone_normalized', $phone)->value('id');
    app(StatsProjector::class)->project($customerId);

    return [$user, $plainToken, $domain, $token, $phone];
}

it('lifecycle step 1: fraud check ingests order as new_order', function () {
    [$user, , , $token, $phone] = seedLifecycleOrder();

    $order = PlatformOrder::query()->where('wc_order_id', 99001)->first();

    expect($order)->not->toBeNull()
        ->and($order->current_status)->toBe(OrderStatus::NewOrder->value)
        ->and($order->access_token_id)->toBe($token->id)
        ->and($order->customer->phone_normalized)->toBe($phone);
});

it('lifecycle step 2: GET /api/intel/customer returns profile', function () {
    [, $plainToken, $domain, , $phone] = seedLifecycleOrder();

    $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/customer?phone=' . $phone)
        ->assertOk()
        ->assertJsonPath('phone', $phone)
        ->assertJsonPath('report.source', 'platform');
});

it('lifecycle step 3: GET /api/intel/dashboard returns merchant stats', function () {
    [, $plainToken, $domain] = seedLifecycleOrder();

    $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/dashboard')
        ->assertOk()
        ->assertJsonPath('summary.total_orders', 1)
        ->assertJsonStructure(['summary', 'status_counts', 'top_products', 'recent_orders']);
});

it('lifecycle step 4: GET /api/intel/suggest returns phone autocomplete', function () {
    [, $plainToken, $domain, , $phone] = seedLifecycleOrder();

    $response = $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/suggest?q=017112');

    $response->assertOk()
        ->assertJsonStructure(['suggestions']);

    $phones = collect($response->json('suggestions'))->pluck('phone');
    expect($phones)->toContain($phone);
});

it('lifecycle step 5: courier shipment moves order to courier_entry', function () {
    [$user, , , $token] = seedLifecycleOrder();

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => 'LC-99001',
        'invoice' => '99001',
        'wc_order_id' => 99001,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lifecycle-shop.example.com',
        'site_domain' => 'lifecycle-shop.example.com',
        'courier_account_id' => 1,
        'status' => 'pending',
    ]);

    app(CourierEntryIngestor::class)->ingestFromShipment($shipment);

    expect(PlatformOrder::query()->where('wc_order_id', 99001)->value('current_status'))
        ->toBe(OrderStatus::CourierEntry->value);
});

it('lifecycle step 6: webhook transitions courier_entry → courier_handover → delivered', function () {
    [$user, , , $token] = seedLifecycleOrder();

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => 'LC-99001-D',
        'invoice' => '99001',
        'wc_order_id' => 99001,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lifecycle-shop.example.com',
        'site_domain' => 'lifecycle-shop.example.com',
        'courier_account_id' => 1,
        'status' => 'in_transit',
    ]);

    app(CourierEntryIngestor::class)->ingestFromShipment($shipment);

    expect(PlatformOrder::query()->where('wc_order_id', 99001)->value('current_status'))
        ->toBe(OrderStatus::CourierEntry->value);

    // Steadfast in_transit maps to courier_handover
    app(WebhookOrderIngestor::class)->ingest($shipment, [
        'raw_status' => 'in_transit',
        'updated_at' => now()->subMinute()->toDateTimeString(),
    ]);

    expect(PlatformOrder::query()->where('wc_order_id', 99001)->value('current_status'))
        ->toBe(OrderStatus::CourierHandover->value);

    app(WebhookOrderIngestor::class)->ingest($shipment, [
        'raw_status' => 'delivered',
        'updated_at' => now()->toDateTimeString(),
    ]);

    expect(PlatformOrder::query()->where('wc_order_id', 99001)->value('current_status'))
        ->toBe(OrderStatus::Delivered->value);
});

it('lifecycle step 7: GET /api/intel/orders lists delivered order', function () {
    [$user, $plainToken, $domain, $token] = seedLifecycleOrder();

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => 'LC-99001-L',
        'invoice' => '99001',
        'wc_order_id' => 99001,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lifecycle-shop.example.com',
        'site_domain' => 'lifecycle-shop.example.com',
        'courier_account_id' => 1,
        'status' => 'delivered',
    ]);

    app(CourierEntryIngestor::class)->ingestFromShipment($shipment);
    app(WebhookOrderIngestor::class)->ingest($shipment, ['raw_status' => 'in_transit', 'updated_at' => now()->subMinute()->toDateTimeString()]);
    app(WebhookOrderIngestor::class)->ingest($shipment, ['raw_status' => 'delivered', 'updated_at' => now()->toDateTimeString()]);

    $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/orders?status=delivered')
        ->assertOk()
        ->assertJsonPath('data.0.wc_order_id', 99001)
        ->assertJsonPath('data.0.current_status', OrderStatus::Delivered->value);
});

it('lifecycle step 8: GET /api/intel/analytics/platform returns summary', function () {
    [, $plainToken, $domain] = seedLifecycleOrder();

    $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/analytics/platform')
        ->assertOk()
        ->assertJsonStructure([
            'total_customers',
            'total_orders',
            'total_revenue',
            'risk_distribution',
            'status_distribution',
        ])
        ->assertJsonPath('total_orders', 1);
});

it('lifecycle step 9: GET /api/intel/ai/customer returns AI profile', function () {
    [, $plainToken, $domain, , $phone] = seedLifecycleOrder();

    $this->withHeaders(lifecycleHeaders($plainToken, $domain))
        ->getJson('/api/intel/ai/customer?phone=' . $phone)
        ->assertOk()
        ->assertJsonStructure(['risk_score', 'recommendation']);
});

it('lifecycle step 10: admin UI reflects ingested data', function () {
    seedLifecycleOrder();

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'lifecycle-admin-' . uniqid() . '@example.com',
        'phone' => '018' . random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('orderIntelligence.ordersList', ['q' => '99001']))
        ->assertOk()
        ->assertJsonPath('data.0.wc_order_id', 99001);

    $this->actingAs($admin)
        ->get(route('orderIntelligence.customerLookup', ['phone' => '01711223344']))
        ->assertOk()
        ->assertJsonPath('phone', '01711223344');
});
