<?php

namespace Database\Seeders;

use App\Models\PackageHub;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        $this->seedPackageHubs();

        $this->command?->info('Required reference tables seeded.');
        $this->command?->comment('Roles: ' . Role::count() . ' | Permissions: ' . Permission::count() . ' | Plans: ' . PackageHub::count());
    }

    private function seedPackageHubs(): void
    {
        $createdBy = User::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->value('id');

        $definitions = [
            'Standard' => [
                'description' => 'Standard order-based subscription',
                'per_order_rate' => 1,
                'is_active' => true,
            ],
            'Premium' => [
                'description' => 'Premium order-based subscription',
                'per_order_rate' => 1.5,
                'is_active' => true,
            ],
        ];

        $index = 1;

        foreach ($definitions as $title => $data) {
            PackageHub::updateOrCreate(
                ['title' => $title],
                [
                    ...$data,
                    'created_by' => $createdBy,
                    'index' => $index,
                ]
            );

            $index++;
        }
    }
}
