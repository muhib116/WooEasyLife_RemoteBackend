<?php

namespace Tests\Feature;

use App\Services\FraudCheckService;
use App\Services\OrderIntelligence\FraudCheckCoordinator;
use App\Services\PublicFraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicFraudCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_stats_endpoint_returns_meta(): void
    {
        $response = $this->getJson(route('landing.fraud-check.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'enabled',
                'daily_free_limit',
                'remaining_searches',
                'daily_search_count',
                'daily_search_label',
                'daily_search_phrase',
            ]);
    }

    public function test_public_fraud_check_increments_daily_count(): void
    {
        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')
                ->once()
                ->with('01770989591')
                ->andReturn('01770989591');
        });

        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')
                ->once()
                ->andReturn([
                    'total_order' => 10,
                    'confirmed' => 8,
                    'frauds' => [
                        [
                            'name' => 'Test Merchant',
                            'details' => 'ফেইক অর্ডার করেছে',
                            'created_at' => '2026-06-30T10:00:00.000000Z',
                            'consignment_id' => 'SF12345',
                        ],
                    ],
                    'cancel' => 2,
                    'success_rate' => '80%',
                    'courier' => [],
                ]);
        });

        $response = $this->postJson(route('landing.fraud-check.check'), [
            'phone' => '01770989591',
        ]);

        $response->assertOk()
            ->assertJsonPath('limited', false)
            ->assertJsonPath('risk_label', 'নিরাপদ গ্রাহক')
            ->assertJsonPath('report.frauds.0.details', 'ফেইক অর্ডার করেছে')
            ->assertJsonPath('meta.used_searches', 1)
            ->assertJsonPath('meta.remaining_searches', 4);
    }

    public function test_public_fraud_check_enforces_daily_limit(): void
    {
        $ip = '203.0.113.10';

        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')->andReturn('01770989591');
        });

        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')->andReturn([
                'total_order' => 0,
                'confirmed' => 0,
                'frauds' => [],
                'cancel' => 0,
                'success_rate' => 'No order history found!',
                'courier' => [],
            ]);
        });

        $service = app(PublicFraudCheckService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->check($ip, '01770989591');
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson(route('landing.fraud-check.check'), [
                'phone' => '01770989591',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('limited', true);
    }

    public function test_invalid_phone_returns_validation_error(): void
    {
        $response = $this->postJson(route('landing.fraud-check.check'), [
            'phone' => '12345',
        ]);

        $response->assertStatus(422);
    }

    public function test_public_fraud_check_uses_hybrid_coordinator(): void
    {
        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')
                ->once()
                ->with('01770989591')
                ->andReturn('01770989591');
        });

        $this->mock(FraudCheckCoordinator::class, function ($mock) {
            $mock->shouldReceive('checkSingle')
                ->once()
                ->withArgs(function (Request $request, array $payload) {
                    return ($payload['phone'] ?? null) === '01770989591';
                })
                ->andReturn([
                    'total_order' => 5,
                    'confirmed' => 4,
                    'cancel' => 1,
                    'success_rate' => '80%',
                    'frauds' => [],
                    'courier' => [
                        [
                            'title' => 'Pathao',
                            'report' => [
                                'data_type' => 'rating',
                                'status' => 'rating_only',
                                'customer_rating' => 'good_customer',
                                'success_rate' => 'Good Customer',
                                'total_order' => 0,
                                'confirmed' => 0,
                                'cancel' => 0,
                            ],
                        ],
                    ],
                    'source' => 'hybrid',
                ]);
        });

        $response = $this->postJson(route('landing.fraud-check.check'), [
            'phone' => '01770989591',
        ]);

        $response->assertOk()
            ->assertJsonPath('report.courier.0.report.data_type', 'rating')
            ->assertJsonPath('report.courier.0.report.success_rate', 'Good Customer');
    }
}
