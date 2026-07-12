<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsitePlanEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_plan_expiry_and_quota_from_websites_flow(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
            'index' => 1,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $expiresAt = now()->addDays(14)->format('Y-m-d');

        $response = $this->actingAs($admin)->post(
            route('users.updatePurchasePackage', $merchant->id),
            [
                'id' => $userPackage->id,
                'domain' => 'shop.example.com',
                'remaining_order' => 40,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'note' => 'Updated from websites card',
            ]
        );

        $response->assertRedirect();

        $userPackage->refresh();

        $this->assertSame(40, $userPackage->remaining_order);
        $this->assertSame('Updated from websites card', $userPackage->note);
        $this->assertEquals(1, $userPackage->is_active);
        $this->assertNotNull($userPackage->expires_at);
        $this->assertSame($expiresAt, $userPackage->expires_at->format('Y-m-d'));
    }

    public function test_admin_can_update_catalog_subscription_features_from_adjust_flow(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-features@example.com',
            'phone' => '01700000088',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-features@example.com',
            'phone' => '01700000007',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_price' => 999,
            'order_rate_token' => 1000,
            'package_duration' => '1_month',
            'is_active' => true,
            'created_by' => $admin->id,
            'index' => 1,
            'features' => PackageCatalogFeatures::starterMap(),
        ]);

        $features = PackageCatalogFeatures::normalize([
            'fraud_customer_checker' => true,
            'bulk_sms' => false,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Starter',
            'domain' => 'localhost',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 1000,
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 1000,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'features' => $features,
            'expires_at' => now()->addMonth(),
        ]);

        $updatedFeatures = array_merge($features, [
            'fraud_customer_checker' => false,
            'sms_management' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.updatePurchasePackage', $merchant->id),
            [
                'id' => $userPackage->id,
                'remaining_order' => 900,
                'is_active' => true,
                'features' => $updatedFeatures,
            ]
        );

        $response->assertRedirect();

        $userPackage->refresh();

        $this->assertSame(900, $userPackage->remaining_order);
        $this->assertFalse($userPackage->features['fraud_customer_checker']);
        $this->assertTrue($userPackage->features['sms_management']);
    }

    public function test_admin_can_update_catalog_subscription_app_connect_from_adjust_flow(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-app-connect@example.com',
            'phone' => '01700000087',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-app-connect@example.com',
            'phone' => '01700000006',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Pro',
            'per_order_rate' => 0,
            'package_price' => 4999,
            'order_rate_token' => 5000,
            'package_duration' => '1_month',
            'app_connect' => true,
            'total_website_connect' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
            'index' => 2,
            'features' => PackageCatalogFeatures::normalize([
                'app_connect' => true,
            ]),
        ]);

        $features = PackageCatalogFeatures::normalize([
            'app_connect' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Pro',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 5000,
            'package_duration' => '1_month',
            'total_order_can_handle' => 5000,
            'remaining_order' => 5000,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'total_cost' => 4999,
            'transaction_charge' => 0,
            'is_active' => true,
            'features' => $features,
            'app_connect' => true,
            'total_website_connect' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $updatedFeatures = array_merge($features, [
            'courier_automation' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.updatePurchasePackage', $merchant->id),
            [
                'id' => $userPackage->id,
                'remaining_order' => 4500,
                'is_active' => true,
                'app_connect' => true,
                'total_website_connect' => 3,
                'features' => $updatedFeatures,
            ]
        );

        $response->assertRedirect();

        $userPackage->refresh();

        $this->assertTrue($userPackage->app_connect);
        $this->assertSame(3, $userPackage->total_website_connect);
        $this->assertTrue($userPackage->features['app_connect']);
        $this->assertArrayNotHasKey('app_store_limit', $userPackage->features);
    }

    public function test_legacy_packages_route_redirects_to_websites(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'phone' => '01700000098',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $response = $this->actingAs($admin)->get(
            route('users.packages', [
                'user_id' => $merchant->id,
                'domain' => 'shop.example.com',
            ])
        );

        $response->assertRedirect(route('users.websites', [
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'action' => 'assign',
        ]));
    }

    public function test_cannot_set_remaining_order_above_plan_quota(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin3@example.com',
            'phone' => '01700000097',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant3@example.com',
            'phone' => '01700000003',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.updatePurchasePackage', $merchant->id),
            [
                'id' => $userPackage->id,
                'remaining_order' => 150,
                'is_active' => true,
            ]
        );

        $response->assertSessionHasErrors('remaining_order');
        $this->assertSame(75, $userPackage->fresh()->remaining_order);
    }

    public function test_cannot_activate_expired_plan_without_extending_expiry(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin4@example.com',
            'phone' => '01700000096',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant4@example.com',
            'phone' => '01700000004',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => false,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.updatePurchasePackage', $merchant->id),
            [
                'id' => $userPackage->id,
                'is_active' => true,
                'remaining_order' => 50,
                'expires_at' => now()->subDay()->format('Y-m-d'),
            ]
        );

        $response->assertSessionHasErrors('expires_at');
        $this->assertFalse((bool) $userPackage->fresh()->is_active);
    }

    public function test_legacy_api_keys_route_redirects_to_websites(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin5@example.com',
            'phone' => '01700000095',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant5@example.com',
            'phone' => '01700000005',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $response = $this->actingAs($admin)->get(
            route('users.apiKeys', [
                'user_id' => $merchant->id,
                'domain' => 'shop.example.com',
            ])
        );

        $response->assertRedirect(route('users.websites', [
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'action' => 'license',
        ]));
    }

    public function test_legacy_sms_recharge_route_redirects_to_sms_tab(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin6@example.com',
            'phone' => '01700000094',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant6@example.com',
            'phone' => '01700000006',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $response = $this->actingAs($admin)->get(
            route('users.smsRecharge', ['user_id' => $merchant->id])
        );

        $response->assertRedirect(route('users.sms', [
            'user_id' => $merchant->id,
            'tab' => 'recharge',
        ]));
    }
}
