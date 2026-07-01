<?php

namespace Tests\Unit;

use App\Support\PackageCatalogFeatures;
use Tests\TestCase;

class PackageCatalogFeaturesTest extends TestCase
{
    public function test_normalize_persists_only_power_keys(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'fraud_customer_checker' => true,
            'bulk_sms' => true,
            'customer_sms_for_order' => false,
        ]);

        $this->assertTrue($normalized['fraud_customer_checker']);
        $this->assertTrue($normalized['sms_management']);
        $this->assertArrayNotHasKey('bulk_sms', $normalized);
        $this->assertCount(count(PackageCatalogFeatures::powerKeys()), $normalized);
    }

    public function test_expand_for_legacy_api_maps_power_keys(): void
    {
        $legacy = PackageCatalogFeatures::expandForLegacyApi([
            'sms_management' => true,
            'courier_automation' => false,
        ]);

        $this->assertTrue($legacy['customer_sms_for_order']);
        $this->assertTrue($legacy['bulk_sms']);
        $this->assertFalse($legacy['courier_entry_automation']);
    }

    public function test_collapse_legacy_granular_keys_to_power_keys(): void
    {
        $power = PackageCatalogFeatures::collapseToPower([
            'customer_sms_for_order' => true,
            'bulk_sms' => false,
            'three_courier_partner_integration' => true,
            'courier_webhook_integrations' => false,
        ]);

        $this->assertTrue($power['sms_management']);
        $this->assertTrue($power['courier_automation']);
    }

    public function test_build_plugin_feature_lines_for_catalog_plan(): void
    {
        $lines = PackageCatalogFeatures::buildPluginFeatureLines([
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'app_connect' => false,
            'features' => [
                'missing_orders' => true,
                'customer_blacklist' => true,
            ],
        ]);

        $this->assertSame(
            [
                'মিসিং অর্ডার',
                'কাস্টমার ব্ল্যাকলিস্ট',
            ],
            $lines,
        );
    }

    public function test_build_plugin_summary_lines_for_catalog_plan(): void
    {
        $lines = PackageCatalogFeatures::buildPluginSummaryLines([
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'app_connect' => true,
            'total_website_connect' => 2,
        ]);

        $this->assertSame(
            [
                '2টি ওয়েবসাইট',
                'মাসিক প্ল্যান',
                '1,000 টোকেন',
            ],
            $lines,
        );
    }

    public function test_build_plugin_feature_lines_for_legacy_plan(): void
    {
        $lines = PackageCatalogFeatures::buildPluginFeatureLines([
            'plan_type' => 'legacy',
            'per_order_rate' => 1,
        ]);

        $this->assertSame([], $lines);
    }
}
