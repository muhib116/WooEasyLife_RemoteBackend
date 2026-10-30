<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Services\LandingPageService;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        LandingPageService::forgetActivePlansCache();
    }

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
            'features' => PackageCatalogFeatures::map(),
        ]);

        $payload = app(LandingPageService::class)->payload();

        $this->assertNotNull($payload['featuredPlan']);
        $this->assertSame('Pro Plus – 1 Month', $payload['featuredPlan']['title']);
        $this->assertNotEmpty($payload['featureHighlights']);
        $this->assertSame('কাস্টম অর্ডার তৈরি', $payload['featureHighlights'][0]['label']);
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
        $funnelsShowcase = collect($payload['featureShowcases'])->firstWhere('id', 'funnels');
        $this->assertNotNull($funnelsShowcase);
        $this->assertSame('Funnels', $funnelsShowcase['badge']);
        $this->assertStringNotContainsString('Beta', json_encode($funnelsShowcase, JSON_UNESCAPED_UNICODE));
        $this->assertTrue($funnelsShowcase['always_show'] ?? false);
        $this->assertNotEmpty($funnelsShowcase['features']);
        $this->assertSame('orange', $funnelsShowcase['accent']);
        $this->assertNotEmpty($payload['stats']);
        $this->assertNotEmpty($payload['fraudBenefitCards']);
        $this->assertNotEmpty($payload['valuePillars']);
        $this->assertCount(1, $payload['plans']);
    }

    public function test_fraud_benefit_card_uses_the_live_daily_limit(): void
    {
        config(['landing.fraud_check.daily_free_limit' => 12]);

        $bn = app(LandingPageService::class)->payload();
        $bnCard = collect($bn['fraudBenefitCards']['cards'])
            ->firstWhere('title', 'ফ্রি-তে কাস্টমারের Courier History দেখুন');

        $this->assertSame(12, $bn['fraudCheck']['daily_free_limit']);
        $this->assertSame(
            'রেজিস্ট্রেশন ছাড়াই নম্বর দিয়ে প্রতিদিন ১২টি ফ্রি চেক করুন।',
            $bnCard['description'] ?? null,
        );

        $en = app(LandingPageService::class)->payload(null, 'en');
        $enCard = collect($en['fraudBenefitCards']['cards'])
            ->firstWhere('title', 'Free courier history checks');

        $this->assertSame(
            'Check by number without registration — 12 free checks per day.',
            $enCard['description'] ?? null,
        );
    }

    public function test_slim_payload_omits_homepage_unused_blobs(): void
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
            'features' => PackageCatalogFeatures::map(),
        ]);

        $payload = app(LandingPageService::class)->payload(null, 'bn', ['slim' => true]);

        $this->assertArrayNotHasKey('courierChargeCalculator', $payload);
        $this->assertArrayNotHasKey('conversionFeatures', $payload);
        $this->assertArrayNotHasKey('featureHighlights', $payload);
        $this->assertNotEmpty($payload['plans']);
        $this->assertArrayHasKey('fraudCheck', $payload);
    }

    public function test_whatsapp_display_phone_uses_international_form(): void
    {
        config(['landing.whatsapp_phone' => '8801770989591']);

        $fromInternational = app(LandingPageService::class)->payload()['whatsappDisplayPhone'] ?? null;

        config(['landing.whatsapp_phone' => '01770989591']);

        $fromLocal = app(LandingPageService::class)->payload()['whatsappDisplayPhone'] ?? null;

        $this->assertSame('+8801770-989591', $fromInternational);
        $this->assertSame('+8801770-989591', $fromLocal);
        $this->assertStringNotContainsString('001770', (string) $fromInternational);
    }

    public function test_marketing_shell_is_lightweight(): void
    {
        $shell = app(LandingPageService::class)->marketingShell();

        $this->assertArrayHasKey('fraudCheck', $shell);
        $this->assertArrayHasKey('whatsappUrl', $shell);
        $this->assertArrayNotHasKey('plans', $shell);
    }
}
