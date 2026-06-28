<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\MerchantEmployeeService;
use App\Services\RbacService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacAndEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createAdmin(?string $roleSlug = null): User
    {
        $roleId = $roleSlug
            ? Role::where('slug', $roleSlug)->where('scope', 'platform')->value('id')
            : null;

        return User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'admin_role_id' => $roleId,
            'status' => true,
        ]);
    }

    private function createMerchant(): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_super_admin_without_role_has_all_permissions(): void
    {
        $admin = $this->createAdmin();
        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->isSuperAdmin($admin));
        $this->assertTrue($admin->hasPermission('payments.approve'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_billing_clerk_has_limited_permissions(): void
    {
        $admin = $this->createAdmin('billing-clerk');
        $rbac = app(RbacService::class);

        $this->assertFalse($rbac->isSuperAdmin($admin));
        $this->assertTrue($admin->hasPermission('payments.approve'));
        $this->assertFalse($admin->hasPermission('roles.manage'));
        $this->assertFalse($admin->hasPermission('employees.manage'));
    }

    public function test_permission_middleware_blocks_unauthorized_admin(): void
    {
        $admin = $this->createAdmin('viewer');

        $response = $this->actingAs($admin)->post(route('users.approvePackagePayment', [
            'payment_id' => 1,
        ]));

        $response->assertForbidden();
    }

    public function test_merchant_employee_crud(): void
    {
        $merchant = $this->createMerchant();
        $role = Role::where('slug', 'merchant-manager')->where('scope', 'merchant')->firstOrFail();

        $employee = app(MerchantEmployeeService::class)->create($merchant, [
            'name' => 'Shop Manager',
            'email' => 'manager@example.com',
            'phone' => '01711111111',
            'role_id' => $role->id,
            'status' => true,
        ]);

        $this->assertDatabaseHas('merchant_employees', [
            'merchant_user_id' => $merchant->id,
            'name' => 'Shop Manager',
            'role_id' => $role->id,
        ]);

        app(MerchantEmployeeService::class)->update($employee, $merchant, [
            'name' => 'Updated Manager',
            'role_id' => $role->id,
            'status' => false,
        ]);

        $this->assertDatabaseHas('merchant_employees', [
            'id' => $employee->id,
            'name' => 'Updated Manager',
            'status' => false,
        ]);
    }

    public function test_employees_page_loads_for_super_admin(): void
    {
        $admin = $this->createAdmin();
        $merchant = $this->createMerchant();

        $response = $this->actingAs($admin)->get(route('users.employees', $merchant->id));

        $response->assertOk();
    }

    public function test_roles_page_requires_roles_manage_permission(): void
    {
        $viewer = $this->createAdmin('viewer');

        $this->actingAs($viewer)->get(route('roles.index'))->assertForbidden();

        $super = $this->createAdmin();
        $this->actingAs($super)->get(route('roles.index'))->assertOk();
    }
}
