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
                'admin_employee_manage' => true,
                'call_history_with_duration' => true,
                'order_source_identifier' => true,
            ],
        ]);

        $payload = app(LandingPageService::class)->payload();

        $this->assertNotNull($payload['featuredPlan']);
        $this->assertSame('Pro Plus – 1 Month', $payload['featuredPlan']['title']);
        $this->assertNotEmpty($payload['featureHighlights']);
        $this->assertStringContainsString('টেক্সট', $payload['featureHighlights'][0]['label']);
        $this->assertNotEmpty($payload['conversionFeatures']);
        $this->assertNotEmpty($payload['heroBullets']);
        $this->assertNotEmpty($payload['hero']);
        $this->assertNotEmpty($payload['roiScenarios']);
        $this->assertNotEmpty($payload['howItWorks']);
        $this->assertNotEmpty($payload['appShowcase']);
        $this->assertNotEmpty($payload['featureShowcases']);
        $this->assertArrayHasKey('pain', $payload['featureShowcases'][0]);
        $this->assertArrayHasKey('read_more', $payload['featureShowcases'][0]);
        $this->assertArrayHasKey('highlights', $payload['featureShowcases'][0]);
        $teamShowcase = collect($payload['featureShowcases'])->firstWhere('id', 'team');
        $this->assertNotNull($teamShowcase);
        $this->assertNotEmpty($teamShowcase['scenario']);
        $this->assertNotEmpty($teamShowcase['read_more']);
        $this->assertNotEmpty($payload['stats']);
        $this->assertNotEmpty($payload['fraudBenefitCards']);
        $this->assertNotEmpty($payload['valuePillars']);
        $this->assertCount(1, $payload['plans']);
    }
}
