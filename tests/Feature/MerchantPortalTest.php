<?php

namespace Tests\Feature;

use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\MerchantEmployeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_merchant_owner_can_login_and_access_portal_dashboard(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'merchant@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/portal');
        $this->actingAs($merchant)
            ->get(route('portal.dashboard'))
            ->assertOk();
    }

    public function test_admin_login_still_goes_to_admin_dashboard(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
    }

    public function test_merchant_cannot_access_admin_dashboard(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($merchant)
            ->get(route('dashboard'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_employee_portal_login_uses_role_permissions(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'owner@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $viewerRole = Role::query()
            ->where('slug', 'merchant-viewer')
            ->where('scope', 'merchant')
            ->firstOrFail();

        app(MerchantEmployeeService::class)->create($merchant, [
            'name' => 'Viewer Staff',
            'email' => 'viewer-staff@example.com',
            'role_id' => $viewerRole->id,
            'status' => true,
            'grant_portal_access' => true,
            'portal_password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'viewer-staff@example.com',
            'password' => 'password123',
        ])->assertRedirect('/portal');

        $staff = User::where('email', 'viewer-staff@example.com')->firstOrFail();

        $this->actingAs($staff)
            ->get(route('portal.websites'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('portal.billing'))
            ->assertForbidden();
    }

    public function test_employee_website_scope_limits_portal_websites(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'owner2@example.com',
            'phone' => '01700000003',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $siteA = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'title' => 'Shop A',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'title' => 'Shop B',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop-a.example.com',
            'website_id' => $siteA->id,
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop-b.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
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
            'email' => 'operator-staff@example.com',
            'role_id' => $operatorRole->id,
            'website_id' => $siteA->id,
            'status' => true,
            'grant_portal_access' => true,
            'portal_password' => 'password123',
        ]);

        $staff = User::where('email', 'operator-staff@example.com')->firstOrFail();

        $response = $this->actingAs($staff)
            ->get(route('portal.websites'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Portal/Websites/Index')
            ->has('websites', 1)
            ->where('websites.0.domain', 'shop-a.example.com'));
    }
}
