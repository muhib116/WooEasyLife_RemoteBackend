<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_page_renders_active_catalog_plans(): void
    {
        PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $response = $this->get(route('pricing'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Pricing/Index')
            ->has('plans', 1)
            ->where('plans.0.title', 'Starter – 1 Month')
            ->has('plans.0.catalog_features', count(config('package_catalog.power_feature_keys', [])))
            ->where('plans.0.catalog_features.0.key', config('package_catalog.power_feature_keys.0'))
            ->has('conversionFeatures'));
    }

    public function test_pricing_page_hides_plans_not_selected_for_the_website(): void
    {
        PackageHub::create([
            'title' => 'Public plan',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'show_on_frontend' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        PackageHub::create([
            'title' => 'Internal plan',
            'per_order_rate' => 0,
            'package_duration' => '1_year',
            'order_rate_token' => 2000,
            'package_price' => 1999,
            'is_active' => true,
            'show_on_frontend' => false,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $this->get(route('pricing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('plans', 1)
                ->where('plans.0.title', 'Public plan'));
    }
}
