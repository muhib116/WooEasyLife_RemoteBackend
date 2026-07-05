<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\MerchantEmployeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_merchant_portal_billing_shows_alerts_and_allows_payment_request(): void
    {
        [$merchant, $plan] = $this->createMerchantWithPlanAndPackage();

        $this->actingAs($merchant)
            ->get(route('portal.billing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Billing/Index')
                ->has('alerts')
                ->where('alerts.0.type', 'quota_low'));

        $response = $this->actingAs($merchant)->post(route('portal.billing.payment-request'), [
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 50,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN123',
            'account_number' => '01700000000',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'status' => 'pending',
            'order_limit' => 50,
        ]);
    }

    public function test_portal_billing_includes_enriched_catalog_plans(): void
    {
        [$merchant] = $this->createMerchantWithPlanAndPackage();

        PackageHub::create([
            'title' => 'Growth – 1 Month',
            'per_order_rate' => 0,
            'package_price' => 2499,
            'package_duration' => '1_month',
            'order_rate_token' => 3000,
            'is_active' => true,
            'index' => 2,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $this->actingAs($merchant)
            ->get(route('portal.billing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('plans', 1)
                ->where('plans.0.duration_label', 'মাসিক প্ল্যান')
                ->where('plans.0.features_heading', 'প্ল্যান ফিচার')
                ->has('plans.0.top_features', 1));
    }

    public function test_portal_payment_request_is_not_blocked_when_plugin_intent_enforcement_enabled(): void
    {
        [$merchant, $plan] = $this->createMerchantWithPlanAndPackage();

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->actingAs($merchant)->post(route('portal.billing.payment-request'), [
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 50,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-PORTAL-ACTIVE',
            'account_number' => '01700000000',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $merchant->id,
            'transaction_id' => 'TXN-PORTAL-ACTIVE',
            'payment_intent' => 'renew',
            'status' => 'pending',
        ]);
    }

    public function test_portal_billing_uses_plan_domains_without_website_row(): void
    {
        [$merchant, $plan] = $this->createMerchantWithPlanAndPackage(createWebsite: false);

        $this->actingAs($merchant)
            ->get(route('portal.billing'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('domains.0', 'shop.example.com')
                ->has('alerts'));
    }

    public function test_merchant_viewer_cannot_submit_payment_request(): void
    {
        [$merchant, $plan] = $this->createMerchantWithPlanAndPackage();

        $viewerRole = Role::query()
            ->where('slug', 'merchant-viewer')
            ->where('scope', 'merchant')
            ->firstOrFail();

        app(MerchantEmployeeService::class)->create($merchant, [
            'name' => 'Viewer Staff',
            'email' => 'viewer-billing@example.com',
            'role_id' => $viewerRole->id,
            'status' => true,
            'grant_portal_access' => true,
            'portal_password' => 'password123',
        ]);

        $staff = User::where('email', 'viewer-billing@example.com')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('portal.billing.payment-request'), [
                'package_hub_id' => $plan->id,
                'domain' => 'shop.example.com',
                'order_limit' => 50,
                'total_amount' => 50,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN123',
                'account_number' => '01700000000',
            ])
            ->assertForbidden();
    }

    public function test_scoped_employee_cannot_submit_payment_for_other_domain(): void
    {
        [$merchant, $plan] = $this->createMerchantWithPlanAndPackage();

        $siteA = \App\Models\Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'title' => 'Shop A',
            'status' => true,
        ]);

        \App\Models\Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'title' => 'Shop B',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop-b.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $operatorRole = Role::query()
            ->where('slug', 'merchant-operator')
            ->where('scope', 'merchant')
            ->firstOrFail();

        app(MerchantEmployeeService::class)->create($merchant, [
            'name' => 'Scoped Operator',
            'email' => 'operator-billing@example.com',
            'role_id' => $operatorRole->id,
            'website_id' => $siteA->id,
            'status' => true,
            'grant_portal_access' => true,
            'portal_password' => 'password123',
        ]);

        $staff = User::where('email', 'operator-billing@example.com')->firstOrFail();

        $this->actingAs($staff)
            ->post(route('portal.billing.payment-request'), [
                'package_hub_id' => $plan->id,
                'domain' => 'shop-b.example.com',
                'order_limit' => 50,
                'total_amount' => 50,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN123',
                'account_number' => '01700000000',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: PackageHub}
     */
    private function createMerchantWithPlanAndPackage(bool $createWebsite = true): array
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
            'index' => 1,
        ]);

        if ($createWebsite) {
            \App\Models\Website::create([
                'user_id' => $merchant->id,
                'domain' => 'shop.example.com',
                'title' => 'Shop',
                'status' => true,
            ]);
        }

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        return [$merchant, $plan];
    }
}
