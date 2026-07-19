<?php

namespace Tests\Unit;

use App\Services\Marketing\SteadfastPublicPricingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastPublicPricingServiceTest extends TestCase
{
    public function test_sync_caches_charges_from_public_api(): void
    {
        Http::fake([
            'www.steadfast.com.bd/welcome/get/pricing-data' => Http::response([
                'charges' => [
                    'samecity_dhaka_150' => 55,
                    'samecity_dhaka_500' => 65,
                    'samecity_dhaka' => 75,
                    'samecity_weight' => 20,
                    'isd_to_sub_500' => 105,
                    'isd_to_sub' => 105,
                    'isd_to_sub_weight' => 20,
                    'isd_to_osd_500' => 115,
                    'isd_to_osd' => 135,
                    'isd_to_osd_weight' => 20,
                ],
                'districts' => [],
            ], 200),
        ]);

        Cache::forget(SteadfastPublicPricingService::CACHE_KEY);

        $service = app(SteadfastPublicPricingService::class);
        $result = $service->sync();

        $this->assertTrue($result['success']);
        $this->assertNotNull($service->cached());
        $this->assertSame(75.0, $service->deliveryCharge('dhaka', 1.0, $result['charges']));
        $this->assertSame(95.0, $service->deliveryCharge('dhaka', 2.0, $result['charges']));
        $this->assertSame(105.0, $service->deliveryCharge('suburb', 0.5, $result['charges']));
        $this->assertSame(135.0, $service->deliveryCharge('outside', 1.0, $result['charges']));
    }

    public function test_calculator_config_marks_steadfast_live_after_sync(): void
    {
        Http::fake([
            'www.steadfast.com.bd/welcome/get/pricing-data' => Http::response([
                'charges' => [
                    'samecity_dhaka_150' => 55,
                    'samecity_dhaka_500' => 65,
                    'samecity_dhaka' => 75,
                    'samecity_weight' => 20,
                    'isd_to_sub_500' => 105,
                    'isd_to_sub' => 105,
                    'isd_to_sub_weight' => 20,
                    'isd_to_osd_500' => 115,
                    'isd_to_osd' => 135,
                    'isd_to_osd_weight' => 20,
                ],
            ], 200),
        ]);

        Cache::forget(SteadfastPublicPricingService::CACHE_KEY);
        app(SteadfastPublicPricingService::class)->sync();

        $config = app(\App\Services\Marketing\CourierPublicRatesService::class)->calculatorConfig();

        $this->assertSame('steadfast_live', $config['couriers']['steadfast']['pricing_mode']);
        $this->assertSame('live', $config['couriers']['steadfast']['source']);
        $this->assertSame('fallback', $config['couriers']['pathao']['source']);
        $this->assertNotEmpty($config['official_links']);
    }
}
