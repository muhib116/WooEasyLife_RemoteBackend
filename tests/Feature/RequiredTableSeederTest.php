<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RequiredTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequiredTableSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_table_seeder_populates_roles_permissions_and_plans(): void
    {
        $this->seed(RequiredTableSeeder::class);

        $this->assertDatabaseHas('permissions', [
            'slug' => 'billing.manage',
        ]);

        $this->assertDatabaseHas('roles', [
            'slug' => 'merchant-manager',
            'scope' => 'merchant',
        ]);

        $managerRole = Role::query()
            ->where('slug', 'merchant-manager')
            ->where('scope', 'merchant')
            ->firstOrFail();

        $this->assertTrue(
            $managerRole->permissions()->where('slug', 'billing.manage')->exists()
        );

        $this->assertFalse(
            Role::query()
                ->where('slug', 'merchant-operator')
                ->where('scope', 'merchant')
                ->firstOrFail()
                ->permissions()
                ->where('slug', 'billing.manage')
                ->exists()
        );

        $this->assertGreaterThanOrEqual(2, PackageHub::query()->where('is_active', true)->count());
        $this->assertGreaterThanOrEqual(17, Permission::count());
    }

    public function test_required_table_seeder_is_idempotent(): void
    {
        $this->seed(RequiredTableSeeder::class);
        $permissionCount = Permission::count();
        $roleCount = Role::count();
        $planCount = PackageHub::count();

        $this->seed(RequiredTableSeeder::class);

        $this->assertSame($permissionCount, Permission::count());
        $this->assertSame($roleCount, Role::count());
        $this->assertSame($planCount, PackageHub::count());
    }
}
