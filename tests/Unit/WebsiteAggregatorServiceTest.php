<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\WebsiteAggregatorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_packages_and_licenses_by_normalized_domain(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'https://shop.example.com',
                'status' => true,
                'last_used_at' => now(),
            ]);
        });

        $websites = app(WebsiteAggregatorService::class)->forUser($user);

        $this->assertCount(1, $websites);
        $this->assertSame('shop.example.com', $websites[0]['domain']);
        $this->assertSame(50, $websites[0]['subscription']['remaining_order']);
        $this->assertArrayHasKey('features', $websites[0]['subscription']);
        $this->assertArrayHasKey('app_connect', $websites[0]['subscription']);
        $this->assertCount(1, $websites[0]['licenses']);
        $this->assertTrue($websites[0]['health']['ready_for_plugin']);
        $this->assertSame('connected', $websites[0]['health']['status']);
    }

    public function test_marks_website_as_configured_before_plugin_connects(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $websites = app(WebsiteAggregatorService::class)->forUser($user);

        $this->assertFalse($websites[0]['health']['ready_for_plugin']);
        $this->assertTrue($websites[0]['health']['configured_for_plugin']);
        $this->assertSame('configured', $websites[0]['health']['status']);
    }

    public function test_marks_expired_subscription_as_disabled_not_configured(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant4@example.com',
            'phone' => '01700000004',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
                'last_used_at' => now(),
            ]);
        });

        $websites = app(WebsiteAggregatorService::class)->forUser($user);

        $this->assertFalse($websites[0]['health']['configured_for_plugin']);
        $this->assertFalse($websites[0]['health']['ready_for_plugin']);
        $this->assertTrue($websites[0]['health']['subscription_expired']);
        $this->assertSame('disabled', $websites[0]['health']['status']);
    }

    public function test_uses_website_record_when_present(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant3@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'title' => 'Main Store',
            'status' => true,
            'is_primary' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://shop.example.com',
            'website_id' => $website->id,
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 25,
            'total_order_handled' => 75,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $websites = app(WebsiteAggregatorService::class)->forUser($user);

        $this->assertCount(1, $websites);
        $this->assertSame($website->id, $websites[0]['id']);
        $this->assertSame('Main Store', $websites[0]['title']);
    }

    public function test_includes_linked_employees_on_website_payload(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $roleId = Role::query()
            ->where('slug', 'merchant-operator')
            ->where('scope', 'merchant')
            ->value('id');

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-employees@example.com',
            'phone' => '01700000005',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $siteA = Website::create([
            'user_id' => $user->id,
            'domain' => 'shop-a.example.com',
            'title' => 'Shop A',
            'status' => true,
        ]);

        $siteB = Website::create([
            'user_id' => $user->id,
            'domain' => 'shop-b.example.com',
            'title' => 'Shop B',
            'status' => true,
        ]);

        $assigned = MerchantEmployee::create([
            'merchant_user_id' => $user->id,
            'role_id' => $roleId,
            'name' => 'Assigned Staff',
            'phone' => '01711111111',
            'status' => true,
        ]);
        $assigned->websites()->sync([$siteA->id]);

        MerchantEmployee::create([
            'merchant_user_id' => $user->id,
            'role_id' => $roleId,
            'name' => 'All Websites Staff',
            'phone' => '01722222222',
            'status' => true,
        ]);

        $inactive = MerchantEmployee::create([
            'merchant_user_id' => $user->id,
            'role_id' => $roleId,
            'name' => 'Inactive Staff',
            'phone' => '01733333333',
            'status' => false,
        ]);
        $inactive->websites()->sync([$siteA->id]);

        $websites = collect(app(WebsiteAggregatorService::class)->forUser($user))
            ->keyBy('domain');

        $shopA = $websites->get('shop-a.example.com');
        $shopB = $websites->get('shop-b.example.com');

        $this->assertSame(2, $shopA['employee_count']);
        $this->assertEqualsCanonicalizing(
            ['Assigned Staff', 'All Websites Staff'],
            collect($shopA['employees'])->pluck('name')->all()
        );

        $this->assertSame(1, $shopB['employee_count']);
        $this->assertSame('All Websites Staff', $shopB['employees'][0]['name']);
    }
}
