<?php

namespace Tests\Feature;

use App\Services\FraudCheckService;
use App\Services\PublicFraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            $mock->shouldReceive('getReport')
                ->once()
                ->with('01770989591')
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
            ->assertJsonPath('meta.daily_search_count', 1)
            ->assertJsonPath('meta.remaining_searches', 4);
    }

    public function test_public_fraud_check_enforces_daily_limit(): void
    {
        $service = app(PublicFraudCheckService::class);
        $ip = '203.0.113.10';

        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')->andReturn('01770989591');
            $mock->shouldReceive('getReport')->andReturn([
                'total_order' => 0,
                'confirmed' => 0,
                'frauds' => [],
                'cancel' => 0,
                'success_rate' => 'No order history found!',
                'courier' => [],
            ]);
        });

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
}
