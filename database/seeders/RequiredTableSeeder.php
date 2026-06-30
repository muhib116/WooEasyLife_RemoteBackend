<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds reference data required after migrations on fresh or existing installs.
 *
 * Safe to re-run: uses updateOrCreate / sync — it will not wipe merchant data.
 *
 * Usage:
 *   php artisan db:seed --class=RequiredTableSeeder
 */
class RequiredTableSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding required reference tables (roles, permissions, plans)...');

        $this->call(RolePermissionSeeder::class);
        $this->call(PackageCatalogSeeder::class);

        $this->command?->info('Required reference tables seeded.');
        $this->command?->comment('Roles: ' . Role::count() . ' | Permissions: ' . Permission::count());
    }
}
