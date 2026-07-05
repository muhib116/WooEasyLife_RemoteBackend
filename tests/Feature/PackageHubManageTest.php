<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PackageHubManageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogPayload(array $overrides = []): array
    {
        return array_merge([
            'package_name' => 'Pro Plus',
            'package_duration' => '1_month',
            'trial_days' => null,
            'order_rate_token' => 1000,
            'package_price' => 1500,
            'description' => '<p>Updated plan</p>',
            'is_active' => true,
            'is_special' => false,
            'app_connect' => true,
            'total_website_connect' => 3,
            'features' => [
                'fraud_customer_checker' => true,
                'one_click_app_connect' => true,
            ],
        ], $overrides);
    }

    public function test_admin_can_update_catalog_package(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 500,
            'package_price' => 500,
            'features' => ['fraud_customer_checker' => true],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.update', $package->id), $this->catalogPayload([
                'package_name' => 'Starter Plus',
                'order_rate_token' => 2000,
                'package_price' => 1999,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $package->refresh();
        $this->assertSame('Starter Plus', $package->title);
        $this->assertSame(2000, $package->order_rate_token);
        $this->assertSame(1999.0, (float) $package->package_price);
    }

    public function test_legacy_package_cannot_be_updated_from_catalog_form(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.update', $package->id), $this->catalogPayload())
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('Standard', $package->fresh()->title);
    }

    public function test_admin_can_soft_delete_unassigned_package(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Delete Me',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.delete', $package->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('package_hubs', ['id' => $package->id]);
    }

    public function test_delete_is_blocked_when_package_is_assigned(): void
    {
        $admin = $this->adminUser();
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Assigned Plan',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => $package->title,
            'user_id' => $merchant->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.delete', $package->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($package->fresh()->deleted_at);
    }

    public function test_admin_can_restore_soft_deleted_package(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Restore Me',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $package->delete();

        $this->actingAs($admin)
            ->post(route('packages.restore', $package->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($package->fresh()->deleted_at);
    }

    public function test_restore_is_blocked_for_active_package(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Active Plan',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.restore', $package->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($package->fresh()->deleted_at);
    }

    public function test_admin_can_toggle_package_status(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Toggle Me',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.toggleStatus', $package->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($package->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('packages.toggleStatus', $package->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($package->fresh()->is_active);
    }

    public function test_toggle_status_is_blocked_for_deleted_package(): void
    {
        $admin = $this->adminUser();

        $package = PackageHub::create([
            'title' => 'Deleted Plan',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        $package->delete();

        $this->actingAs($admin)
            ->post(route('packages.toggleStatus', $package->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($package->fresh()->is_active);
    }

    public function test_package_index_includes_subscription_counts(): void
    {
        $admin = $this->adminUser();
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-count@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Counted Plan',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'package_price' => 100,
            'features' => [],
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => $package->title,
            'user_id' => $merchant->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => $package->title,
            'user_id' => $merchant->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 0,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('packages.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Package/Index')
                ->has('packages')
                ->where('packages', function ($packages) use ($package) {
                    $match = collect($packages)->firstWhere('id', $package->id);

                    return $match
                        && ($match['subscriptions_count'] ?? null) === 2
                        && ($match['active_subscriptions_count'] ?? null) === 1;
                }));
    }
}
