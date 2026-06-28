<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
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
