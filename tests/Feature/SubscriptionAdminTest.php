<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    private function createMerchant(): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    private function createCatalogPlan(array $overrides = []): PackageHub
    {
        return PackageHub::create(array_merge([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'index' => 1,
        ], $overrides));
    }

    public function test_admin_can_renew_catalog_subscription(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchant();
        $plan = $this->createCatalogPlan();

        $userPackage = UserPackage::create([
            'title' => $plan->title,
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 1000,
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 50,
            'total_order_handled' => 950,
            'total_cost' => 999,
            'per_order_rate' => 0,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.renewSubscription', $merchant->id),
            ['user_package_id' => $userPackage->id]
        );

        $response->assertRedirect();
        $userPackage->refresh();

        $this->assertSame(1000, $userPackage->remaining_order);
        $this->assertSame(0, $userPackage->total_order_handled);
        $this->assertTrue($userPackage->expires_at->greaterThan(now()));
    }

    public function test_legacy_renew_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchant();
        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'legacy',
            'total_order_can_handle' => 300,
            'remaining_order' => 10,
            'total_order_handled' => 290,
            'per_order_rate' => 2,
            'total_cost' => 600,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.renewSubscription', $merchant->id),
            ['user_package_id' => $userPackage->id]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('subscription');
    }

    public function test_admin_can_change_catalog_plan(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchant();
        $starter = $this->createCatalogPlan(['title' => 'Starter', 'order_rate_token' => 500]);
        $pro = $this->createCatalogPlan([
            'title' => 'Pro – 1 Month',
            'order_rate_token' => 2000,
            'package_price' => 1999,
            'index' => 2,
        ]);

        $userPackage = UserPackage::create([
            'title' => $starter->title,
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $starter->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 500,
            'package_duration' => '1_month',
            'total_order_can_handle' => 500,
            'remaining_order' => 100,
            'total_order_handled' => 400,
            'total_cost' => 999,
            'per_order_rate' => 0,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.changeSubscription', $merchant->id),
            [
                'user_package_id' => $userPackage->id,
                'package_id' => $pro->id,
                'domain' => 'shop.example.com',
                'transaction_method' => 'Cash',
            ]
        );

        $response->assertRedirect();
        $userPackage->refresh();

        $this->assertSame($pro->id, $userPackage->package_hub_id);
        $this->assertSame('Pro – 1 Month', $userPackage->title);
        $this->assertSame(2000, $userPackage->remaining_order);
        $this->assertSame(0, $userPackage->total_order_handled);
    }

    public function test_change_from_legacy_to_catalog_deactivates_old_and_creates_new(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchant();
        $legacyPlan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);
        $catalogPlan = $this->createCatalogPlan();

        $existing = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $legacyPlan->id,
            'plan_type' => 'legacy',
            'total_order_can_handle' => 300,
            'remaining_order' => 50,
            'per_order_rate' => 2,
            'total_cost' => 600,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.changeSubscription', $merchant->id),
            [
                'user_package_id' => $existing->id,
                'package_id' => $catalogPlan->id,
                'domain' => 'shop.example.com',
                'transaction_method' => 'Cash',
            ]
        );

        $response->assertRedirect();

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_active);

        $newPackage = UserPackage::query()
            ->where('user_id', $merchant->id)
            ->where('is_active', true)
            ->where('plan_type', 'catalog')
            ->first();

        $this->assertNotNull($newPackage);
        $this->assertNotSame($existing->id, $newPackage->id);
        $this->assertSame('catalog', $newPackage->plan_type);
    }
}
