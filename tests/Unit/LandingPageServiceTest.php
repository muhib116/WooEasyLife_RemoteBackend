<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Services\LandingPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_feature_highlights_from_featured_plan(): void
    {
        PackageHub::create([
            'title' => 'Pro Plus – 1 Month',
            'description' => '<p>Full plan</p>',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 10000,
            'package_price' => 4999,
            'app_connect' => true,
            'is_special' => true,
            'is_active' => true,
            'features' => [
                'fraud_customer_checker' => true,
                'ai_text_order_create' => true,
                'ai_image_to_order_create' => true,
                'common_dashboard' => true,
                'multistore_order_notifications' => true,
                'three_courier_partner_integration' => true,
                'customer_sms_for_order' => true,
            ],
        ]);

        $payload = app(LandingPageService::class)->payload();

        $this->assertNotNull($payload['featuredPlan']);
        $this->assertSame('Pro Plus – 1 Month', $payload['featuredPlan']['title']);
        $this->assertNotEmpty($payload['featureHighlights']);
        $this->assertStringContainsString('টেক্সট', $payload['featureHighlights'][0]['label']);
        $this->assertNotEmpty($payload['conversionFeatures']);
        $this->assertNotEmpty($payload['heroBullets']);
        $this->assertNotEmpty($payload['valuePillars']);
        $this->assertSame('ai', $payload['valuePillars'][0]['id']);
        $this->assertNotEmpty($payload['stats']);
        $this->assertCount(1, $payload['plans']);
    }
}
