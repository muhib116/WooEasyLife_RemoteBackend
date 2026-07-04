<?php

namespace Tests\Unit;

use App\Support\PlanDisplayPresenter;
use Tests\TestCase;

class PlanDisplayPresenterTest extends TestCase
{
    public function test_enrich_catalog_plan_includes_dynamic_bangla_display_fields(): void
    {
        $enriched = PlanDisplayPresenter::enrich([
            'id' => 1,
            'title' => 'Starter – 1 Month',
            'description' => '<p>Essential tools</p>',
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'package_price' => 999,
            'order_rate_token' => 1000,
            'app_connect' => false,
            'is_special' => false,
            'features' => [
                'fraud_customer_checker' => true,
                'sms_management' => true,
                'missing_orders' => true,
            ],
        ]);

        $this->assertSame('মাসিক প্ল্যান', $enriched['duration_label']);
        $this->assertSame('৳999', $enriched['price_label']);
        $this->assertSame('1,000 টোকেন', $enriched['token_label']);
        $this->assertSame('যা পাবেন', $enriched['features_heading']);
        $this->assertCount(3, $enriched['all_features']);
        $this->assertCount(3, $enriched['top_features']);
        $this->assertSame(3, $enriched['enabled_feature_count']);
        $this->assertNull($enriched['more_features_label']);
        $this->assertSame('ফ্রড কাস্টমার চেকার', $enriched['top_features'][0]['label']);
        $this->assertSame(
            ['ফ্রড কাস্টমার চেকার', 'মিসিং অর্ডার', 'এসএমএস ম্যানেজমেন্ট'],
            $enriched['feature_lines'],
        );
    }

    public function test_enrich_special_plan_includes_badge_and_more_features_label(): void
    {
        $features = [];
        foreach (config('package_catalog.power_feature_keys', []) as $index => $key) {
            $features[$key] = $index < 7;
        }

        $enriched = PlanDisplayPresenter::enrich([
            'id' => 2,
            'title' => 'Pro Plus – 1 Month',
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'package_price' => 4999,
            'order_rate_token' => 10000,
            'app_connect' => true,
            'total_website_connect' => 3,
            'is_special' => true,
            'features' => $features,
        ], topFeatureLimit: 5);

        $this->assertSame('সবচেয়ে জনপ্রিয়', $enriched['badge_label']);
        $this->assertSame('মোবাইল অ্যাপ অন্তর্ভুক্ত', $enriched['app_connect_label']);
        $this->assertCount(5, $enriched['top_features']);
        $this->assertSame(7, $enriched['enabled_feature_count']);
        $this->assertSame(2, $enriched['more_features_count']);
        $this->assertSame('+ আরও 2 ফিচার', $enriched['more_features_label']);
    }
}
