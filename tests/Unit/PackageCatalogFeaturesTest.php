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
                'কাস্টমার ব্ল্যাকলিস্ট',
                'মিসিং অর্ডার',
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

    public function test_normalize_includes_all_power_keys_with_defaults(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'fraud_customer_checker' => true,
        ]);

        $this->assertCount(count(PackageCatalogFeatures::powerKeys()), $normalized);
        $this->assertTrue($normalized['fraud_customer_checker']);
        $this->assertArrayHasKey('create_order', $normalized);
        $this->assertArrayHasKey('order_cloning', $normalized);
        $this->assertArrayHasKey('call_and_status_log', $normalized);
        $this->assertArrayHasKey('customer_delivery_history', $normalized);
        $this->assertArrayHasKey('customer_behavior', $normalized);
        $this->assertArrayHasKey('pixel_protection', $normalized);
        $this->assertArrayHasKey('parcel_note_history', $normalized);
    }

    public function test_normalize_infers_split_keys_from_legacy_parent_toggles(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'ai_intelligence' => true,
            'app_connect' => true,
            'missing_orders' => true,
            'courier_automation' => true,
        ]);

        $this->assertTrue($normalized['customer_delivery_history']);
        $this->assertTrue($normalized['customer_behavior']);
        $this->assertFalse($normalized['create_order']);
        $this->assertTrue($normalized['call_and_status_log']);
        $this->assertTrue($normalized['parcel_note_history']);
        $this->assertTrue($normalized['messenger_sales_agent']);
    }

    public function test_normalize_infers_messenger_sales_agent_from_create_order(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'create_order' => true,
        ]);

        $this->assertTrue($normalized['messenger_sales_agent']);
    }

    public function test_normalize_respects_explicit_messenger_sales_agent_false(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'create_order' => true,
            'messenger_sales_agent' => false,
        ]);

        $this->assertTrue($normalized['create_order']);
        $this->assertFalse($normalized['messenger_sales_agent']);
    }

    public function test_normalize_respects_explicit_parcel_note_history_false(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'courier_automation' => true,
            'parcel_note_history' => false,
        ]);

        $this->assertTrue($normalized['courier_automation']);
        $this->assertFalse($normalized['parcel_note_history']);
    }

    public function test_expand_for_legacy_api_includes_new_power_keys(): void
    {
        $legacy = PackageCatalogFeatures::expandForLegacyApi([
            'create_order' => true,
            'order_cloning' => true,
            'call_and_status_log' => true,
            'customer_delivery_history' => true,
            'customer_behavior' => true,
            'pixel_protection' => true,
            'parcel_note_history' => true,
        ]);

        $this->assertTrue($legacy['customer_order_create']);
        $this->assertTrue($legacy['order_cloning']);
        $this->assertTrue($legacy['call_history_with_duration']);
        $this->assertTrue($legacy['customer_delivery_history']);
        $this->assertTrue($legacy['customer_behavior_track']);
        $this->assertTrue($legacy['pixel_protection']);
        $this->assertTrue($legacy['parcel_note_history']);
    }

    public function test_power_keys_include_parcel_note_history(): void
    {
        $keys = PackageCatalogFeatures::powerKeys();

        $this->assertContains('parcel_note_history', $keys);
        $this->assertSame(
            'পার্সেল নোট হিস্ট্রি',
            PackageCatalogFeatures::powerLabelsBn()['parcel_note_history'],
        );
        $this->assertSame(
            'Parcel note history',
            PackageCatalogFeatures::powerLabelsEn()['parcel_note_history'],
        );
    }

    public function test_map_defaults_all_power_keys_to_true(): void
    {
        $features = PackageCatalogFeatures::map(default: true);

        foreach (PackageCatalogFeatures::powerKeys() as $key) {
            $this->assertTrue($features[$key], "Expected {$key} to default to true");
        }
    }

    public function test_normalize_strips_removed_app_store_limit_key(): void
    {
        $normalized = PackageCatalogFeatures::normalize([
            'app_connect' => true,
            'app_store_limit' => true,
            'fraud_customer_checker' => true,
        ]);

        $this->assertTrue($normalized['app_connect']);
        $this->assertTrue($normalized['fraud_customer_checker']);
        $this->assertArrayNotHasKey('app_store_limit', $normalized);
        $this->assertNotContains('app_store_limit', PackageCatalogFeatures::powerKeys());
    }

    public function test_power_keys_include_unlimited_connectivity_features(): void
    {
        $keys = PackageCatalogFeatures::powerKeys();

        $this->assertContains('unlimited_website_connectivity', $keys);
        $this->assertContains('unlimited_app_connectivity', $keys);
        $this->assertSame(
            'অ্যাপে আনলিমিটেড ওয়েবসাইট কানেক্টিভিটি',
            PackageCatalogFeatures::powerLabelsBn()['unlimited_website_connectivity'],
        );
        $this->assertSame(
            'Unlimited app connectivity with website',
            PackageCatalogFeatures::powerLabelsEn()['unlimited_app_connectivity'],
        );

        $normalized = PackageCatalogFeatures::normalize([
            'unlimited_website_connectivity' => true,
            'unlimited_app_connectivity' => false,
        ]);

        $this->assertTrue($normalized['unlimited_website_connectivity']);
        $this->assertFalse($normalized['unlimited_app_connectivity']);
    }
}
