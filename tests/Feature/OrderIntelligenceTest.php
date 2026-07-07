<?php

use App\Domain\OrderIntelligence\OrderStatus;
use App\Jobs\OrderIntelligence\RefreshCourierSnapshotsJob;
use App\Models\AccessToken;
use App\Models\CourierShipment;
use App\Models\OrderIntelligence\CourierCustomerSnapshot;
use App\Models\OrderIntelligence\CourierFraudReport;
use App\Models\OrderIntelligence\PlatformCustomer;
use App\Models\OrderIntelligence\PlatformCustomerStats;
use App\Models\OrderIntelligence\PlatformOrder;
use App\Models\User;
use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\AiIntelligenceService;
use App\Services\OrderIntelligence\CustomerRiskScorer;
use App\Services\OrderIntelligence\CourierEntryIngestor;
use App\Services\OrderIntelligence\CourierStatusMapper;
use App\Services\OrderIntelligence\CustomerResolver;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use App\Services\OrderIntelligence\FraudCheckIngestor;
use App\Services\OrderIntelligence\FraudCheckOrderContext;
use App\Services\OrderIntelligence\IntelligenceSuggestService;
use App\Services\OrderIntelligence\LegacyReportFormatter;
use App\Services\OrderIntelligence\MerchantIntelligenceService;
use App\Services\OrderIntelligence\PlatformIntelligenceReader;
use App\Services\OrderIntelligence\ProductDemandAnalyticsService;
use App\Services\OrderIntelligence\Search\CustomerSearchIndexer;
use App\Services\OrderIntelligence\StatsProjector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;

function createOrderIntelligenceToken(): array
{
    $user = User::create([
        'name' => 'Merchant',
        'email' => 'merchant-intel-' . uniqid() . '@example.com',
        'phone' => '017' . random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    $token = AccessToken::unguarded(function () use ($user) {
        return AccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test-token',
            'token' => hash('sha256', 'plain-token-' . uniqid()),
            'abilities' => ['*'],
            'status' => true,
            'domain' => 'shop.example.com',
        ]);
    });

    return [$user, $token];
}

it('creates a global customer and stats row from phone', function () {
    $customer = app(CustomerResolver::class)->resolve('01712345678', 'Rahim', 'Dhaka');

    expect($customer->phone_normalized)->toBe('01712345678')
        ->and($customer->latest_name)->toBe('Rahim')
        ->and(PlatformCustomerStats::query()->where('platform_customer_id', $customer->id)->exists())->toBeTrue();
});

it('ingests fraud check data with order details and courier fraud notes', function () {
    [$user, $token] = createOrderIntelligenceToken();

    $context = new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01712345678',
        wcOrderId: 4521,
        externalRef: '4521',
        name: 'Karim',
        address: 'Mirpur',
        productTitle: 'Smart Watch',
        orderAmount: 1299.00,
    );

    app(FraudCheckIngestor::class)->ingest(
        $context,
        [
            'total_order' => 9,
            'confirmed' => 7,
            'cancel' => 2,
            'success_rate' => '78%',
            'frauds' => [[
                'name' => 'ABC Store',
                'details' => 'Fake order',
                'consignment_id' => 'SF123',
                'created_at' => '2026-06-30T10:00:00.000000Z',
            ]],
        ],
        ['total_order' => 10, 'confirmed' => 8, 'cancel' => 2, 'success_rate' => '80%'],
        ['total_order' => 4, 'confirmed' => 3, 'cancel' => 1, 'success_rate' => '75%'],
    );

    $customer = PlatformCustomer::query()->where('phone_normalized', '01712345678')->first();

    expect($customer)->not->toBeNull()
        ->and(PlatformOrder::query()->where('wc_order_id', 4521)->value('current_status'))->toBe(OrderStatus::NewOrder->value)
        ->and(CourierFraudReport::query()->where('platform_customer_id', $customer->id)->count())->toBe(1);

    app(StatsProjector::class)->project($customer->id);

    $intel = app(PlatformIntelligenceReader::class)->forPhone('01712345678', $token->id);

    expect($intel['platform_intelligence']['total_orders'])->toBe(1)
        ->and($intel['your_store']['total_orders'])->toBe(1)
        ->and($intel['courier_fraud_notes'])->toHaveCount(1);
});

it('maps courier webhook statuses to canonical statuses', function () {
    $mapper = app(CourierStatusMapper::class);

    expect($mapper->map('steadfast', 'delivered'))->toBe(OrderStatus::Delivered)
        ->and($mapper->map('pathao', 'order.delivered'))->toBe(OrderStatus::Delivered)
        ->and($mapper->map('redx', 'returned'))->toBe(OrderStatus::Returned);
});

it('serves fraud check from platform cache without external courier calls', function () {
    config(['order_intelligence.fraud_check.mode' => 'hybrid']);

    [$user, $token] = createOrderIntelligenceToken();
    $customer = app(CustomerResolver::class)->resolve('01799887766', 'Platform User');

    CourierCustomerSnapshot::query()->create([
        'platform_customer_id' => $customer->id,
        'phone_normalized' => '01799887766',
        'courier' => 'steadfast',
        'total_order' => 10,
        'confirmed' => 8,
        'cancel' => 2,
        'success_rate' => '80%',
        'frauds_count' => 0,
        'fetched_at' => now(),
    ]);

    CourierCustomerSnapshot::query()->create([
        'platform_customer_id' => $customer->id,
        'phone_normalized' => '01799887766',
        'courier' => 'pathao',
        'total_order' => 5,
        'confirmed' => 4,
        'cancel' => 1,
        'success_rate' => '80%',
        'frauds_count' => 0,
        'fetched_at' => now(),
    ]);

    app(StatsProjector::class)->project($customer->id);

    $this->mock(FraudCheckService::class, function ($mock) {
        $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => $phone);
        $mock->shouldNotReceive('getReport');
    });

    Bus::fake();

    $request = Request::create('/api/fraud-check', 'POST', [
        'phone' => '01799887766',
        'wc_order_id' => 9001,
    ]);
    $request->headers->set('Authorization', 'Bearer test-token');

    AccessToken::unguarded(function () use ($token) {
        $token->token = hash('sha256', 'test-token');
        $token->save();
    });

    $report = app(FraudCheckCoordinator::class)->checkSingle($request, $request->all());

    expect($report['source'])->toBe('platform')
        ->and($report['total_order'])->toBeGreaterThan(0)
        ->and($report['courier'][0]['report']['source'] ?? null)->toBe('platform_cache');

    Bus::assertNotDispatched(RefreshCourierSnapshotsJob::class);
});

it('formats legacy fraud check response from platform intelligence', function () {
    $formatted = app(LegacyReportFormatter::class)->format([
        'platform_intelligence' => [
            'counts' => ['new_order' => 3, 'delivered' => 2, 'returned' => 1, 'canceled' => 0],
            'rates' => ['delivery_rate' => '67%'],
            'total_orders' => 3,
            'total_merchants' => 2,
            'risk_tier' => 'caution',
            'data_freshness' => now()->toIso8601String(),
        ],
        'courier_stats' => [[
            'courier' => 'steadfast',
            'total_order' => 6,
            'confirmed' => 5,
            'cancel' => 1,
            'success_rate' => '83%',
            'fetched_at' => now()->toIso8601String(),
        ]],
        'courier_fraud_notes' => [[
            'name' => 'Store',
            'details' => 'Fake order',
            'consignment_id' => 'SF1',
            'created_at' => now()->toIso8601String(),
        ]],
        'your_store' => null,
    ]);

    expect($formatted['source'])->toBe('platform')
        ->and($formatted['frauds'])->toHaveCount(1)
        ->and($formatted['total_order'])->toBe(6);
});

it('returns phone suggestions for autocomplete', function () {
    $customer = app(CustomerResolver::class)->resolve('01755554444', 'Suggest User');
    app(StatsProjector::class)->project($customer->id);

    $result = app(IntelligenceSuggestService::class)->suggest('017555', 5);

    expect($result['suggestions'])->not->toBeEmpty()
        ->and($result['suggestions'][0]['phone'])->toBe('01755554444');
});

it('marks courier entry when shipment is created for an existing platform order', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01744443333',
        wcOrderId: 7001,
    ));

    $order = PlatformOrder::query()->where('wc_order_id', 7001)->firstOrFail();

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => 'SF-7001',
        'invoice' => '7001',
        'wc_order_id' => 7001,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://shop.example.com',
        'site_domain' => 'shop.example.com',
        'courier_account_id' => 1,
        'status' => 'pending',
    ]);

    app(CourierEntryIngestor::class)->ingestFromShipment($shipment);

    expect($order->fresh()->current_status)->toBe(OrderStatus::CourierEntry->value);
});

it('indexes customers for search after stats projection', function () {
    config(['order_intelligence.search.driver' => 'database']);

    $customer = app(CustomerResolver::class)->resolve('01766665555', 'Index User');
    app(StatsProjector::class)->project($customer->id);

    app(CustomerSearchIndexer::class)->indexCustomer($customer->id);

    $result = app(IntelligenceSuggestService::class)->suggest('017666', 5);

    expect($result['driver'])->toBe('database')
        ->and($result['suggestions'])->not->toBeEmpty();
});

it('searches via meilisearch when configured', function () {
    config([
        'order_intelligence.search.driver' => 'meilisearch',
        'order_intelligence.search.meilisearch.host' => 'http://meilisearch.test',
    ]);

    Http::fake([
        'meilisearch.test/indexes/platform_customers/search' => Http::response([
            'hits' => [[
                'phone' => '01711112222',
                'name' => 'Meili User',
                'total_orders' => 4,
                'risk_tier' => 'safe',
                'delivery_rate' => '90%',
                'label' => '01711112222 — Meili User (4 orders)',
            ]],
        ]),
    ]);

    $result = app(IntelligenceSuggestService::class)->suggest('017111', 5);

    expect($result['driver'])->toBe('meilisearch')
        ->and($result['suggestions'][0]['phone'])->toBe('01711112222');
});

it('returns merchant dashboard intelligence', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01733332222',
        wcOrderId: 8001,
        productTitle: 'Premium Watch',
        orderAmount: 2500,
    ));

    app(StatsProjector::class)->project(
        PlatformCustomer::query()->where('phone_normalized', '01733332222')->value('id'),
    );

    $dashboard = app(MerchantIntelligenceService::class)->dashboard($token->id);

    expect($dashboard['summary']['total_orders'])->toBe(1)
        ->and($dashboard['top_products'])->not->toBeEmpty()
        ->and($dashboard['recent_orders'])->toHaveCount(1);
});

it('returns customer profile from intelligence api', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingest(
        new FraudCheckOrderContext(
            accessTokenId: $token->id,
            userId: $user->id,
            phone: '01722221111',
            wcOrderId: 8100,
        ),
        ['total_order' => 3, 'confirmed' => 2, 'cancel' => 1, 'success_rate' => '67%', 'frauds' => []],
        ['total_order' => 0, 'confirmed' => 0, 'cancel' => 0],
        ['total_order' => 0, 'confirmed' => 0, 'cancel' => 0],
    );

    $customerId = PlatformCustomer::query()->where('phone_normalized', '01722221111')->value('id');
    app(StatsProjector::class)->project($customerId);

    $profile = app(MerchantIntelligenceService::class)->customerProfile('01722221111', $token->id);

    expect($profile['phone'])->toBe('01722221111')
        ->and($profile['report']['source'])->toBe('platform');
});

it('computes risk score and ai profile during stats projection', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01788889999',
        wcOrderId: 9009,
        orderAmount: 1500,
    ));

    $customerId = PlatformCustomer::query()->where('phone_normalized', '01788889999')->value('id');
    app(StatsProjector::class)->project($customerId);

    $stats = PlatformCustomerStats::query()->where('platform_customer_id', $customerId)->first();

    expect($stats->risk_score)->not->toBeNull()
        ->and($stats->ai_profile)->toBeArray()
        ->and($stats->ai_profile)->toHaveKeys(['risk_score', 'risk_factors', 'rfm']);
});

it('returns ai customer profile with recommendation', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01777778888',
        wcOrderId: 9010,
    ));

    $customerId = PlatformCustomer::query()->where('phone_normalized', '01777778888')->value('id');
    app(StatsProjector::class)->project($customerId);

    $profile = app(AiIntelligenceService::class)->customerAiProfile('01777778888', $token->id);

    expect($profile['risk_score'])->not->toBeNull()
        ->and($profile['recommendation'])->toHaveKeys(['action', 'label', 'tone']);
});

it('exports paginated ai feature rows for merchant', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01766667777',
        wcOrderId: 9011,
    ));

    $customerId = PlatformCustomer::query()->where('phone_normalized', '01766667777')->value('id');
    app(StatsProjector::class)->project($customerId);

    $export = app(AiIntelligenceService::class)->exportFeatures($token->id);

    expect($export['data'])->toHaveCount(1)
        ->and($export['data'][0]['features'])->toHaveKey('rfm');
});

it('returns product demand analytics for merchant and global scope', function () {
    [$user, $token] = createOrderIntelligenceToken();

    app(FraudCheckIngestor::class)->ingestOrderContext(new FraudCheckOrderContext(
        accessTokenId: $token->id,
        userId: $user->id,
        phone: '01755556666',
        wcOrderId: 9012,
        productTitle: 'Bluetooth Earbuds',
        orderAmount: 999,
    ));

    $merchant = app(ProductDemandAnalyticsService::class)->merchantProducts($token->id);
    $global = app(ProductDemandAnalyticsService::class)->globalProducts();
    $summary = app(ProductDemandAnalyticsService::class)->platformSummary();

    expect($merchant['products'][0]['product_title'])->toBe('Bluetooth Earbuds')
        ->and($global['products'])->not->toBeEmpty()
        ->and($summary['total_orders'])->toBeGreaterThan(0);
});

it('scores higher risk for customers with fraud reports', function () {
    $customer = app(CustomerResolver::class)->resolve('01700001111');

    $scorer = app(CustomerRiskScorer::class);
    $base = $scorer->score($customer->id, ['new_order' => 5, 'delivered' => 3, 'returned' => 2, 'canceled' => 0], [
        'delivery_rate' => '60%',
        'return_rate' => '40%',
        'cancel_rate' => '0%',
        'confirmation_rate' => '100%',
    ], 5, 5000, null, now()->toIso8601String());

    CourierFraudReport::query()->create([
        'platform_customer_id' => $customer->id,
        'phone_normalized' => '01700001111',
        'courier' => 'steadfast',
        'details' => 'Fake order',
        'fingerprint' => hash('sha256', 'test-fraud'),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $withFraud = $scorer->score($customer->id, ['new_order' => 5, 'delivered' => 3, 'returned' => 2, 'canceled' => 0], [
        'delivery_rate' => '60%',
        'return_rate' => '40%',
        'cancel_rate' => '0%',
        'confirmation_rate' => '100%',
    ], 5, 5000, null, now()->toIso8601String());

    expect($withFraud['risk_score'])->toBeGreaterThan($base['risk_score']);
});
