<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Services\PackagePlanResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackagePlanResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_catalog_and_legacy_plans(): void
    {
        $resolver = app(PackagePlanResolver::class);

        $legacy = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $catalog = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
        ]);

        $this->assertTrue($resolver->isLegacy($legacy));
        $this->assertFalse($resolver->isCatalog($legacy));
        $this->assertTrue($resolver->isCatalog($catalog));
        $this->assertFalse($resolver->isLegacy($catalog));
    }

    public function test_plan_payload_includes_catalog_fields(): void
    {
        $resolver = app(PackagePlanResolver::class);

        $catalog = PackageHub::create([
            'title' => 'Pro Plus',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 10000,
            'package_price' => 4999,
            'is_active' => true,
        ]);

        $payload = $resolver->toPlanPayload($catalog);

        $this->assertSame('catalog', $payload['plan_type']);
        $this->assertSame(4999.0, $payload['package_price']);
        $this->assertSame(10000, $payload['order_rate_token']);
        $this->assertFalse($payload['requires_order_limit']);
    }

    public function test_map_plans_for_display_enriches_catalog_plans(): void
    {
        $resolver = app(PackagePlanResolver::class);

        $catalog = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $display = $resolver->mapPlansForDisplay([$catalog]);

        $this->assertCount(1, $display);
        $this->assertSame('মাসিক প্ল্যান', $display[0]['duration_label']);
        $this->assertSame('যা পাবেন', $display[0]['features_heading']);
        $this->assertSame('ফ্রড কাস্টমার চেকার', $display[0]['top_features'][0]['label']);
    }
}
