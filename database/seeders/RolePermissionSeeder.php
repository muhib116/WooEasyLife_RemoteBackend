<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'View dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard'],
            ['name' => 'View merchants', 'slug' => 'merchants.view', 'group' => 'merchants'],
            ['name' => 'Manage merchants', 'slug' => 'merchants.manage', 'group' => 'merchants'],
            ['name' => 'View websites', 'slug' => 'websites.view', 'group' => 'websites'],
            ['name' => 'Manage websites', 'slug' => 'websites.manage', 'group' => 'websites'],
            ['name' => 'View licenses', 'slug' => 'licenses.view', 'group' => 'licenses'],
            ['name' => 'Manage licenses', 'slug' => 'licenses.manage', 'group' => 'licenses'],
            ['name' => 'View billing', 'slug' => 'billing.view', 'group' => 'billing'],
            ['name' => 'Manage billing', 'slug' => 'billing.manage', 'group' => 'billing'],
            ['name' => 'Approve billing', 'slug' => 'billing.approve', 'group' => 'billing'],
            ['name' => 'View payments queue', 'slug' => 'payments.view', 'group' => 'payments'],
            ['name' => 'Approve payments', 'slug' => 'payments.approve', 'group' => 'payments'],
            ['name' => 'View SMS', 'slug' => 'sms.view', 'group' => 'sms'],
            ['name' => 'Manage SMS', 'slug' => 'sms.manage', 'group' => 'sms'],
            ['name' => 'View employees', 'slug' => 'employees.view', 'group' => 'employees'],
            ['name' => 'Manage employees', 'slug' => 'employees.manage', 'group' => 'employees'],
            ['name' => 'Manage roles', 'slug' => 'roles.manage', 'group' => 'settings'],
            ['name' => 'Edit Wise knowledge', 'slug' => 'wise.knowledge.edit', 'group' => 'wise'],
            ['name' => 'Publish Wise knowledge', 'slug' => 'wise.knowledge.publish', 'group' => 'wise'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        $allPermissionIds = Permission::pluck('id', 'slug');

        $platformRoles = [
            'super-admin' => [
                'name' => 'Super Admin',
                'description' => 'Full platform access',
                'permissions' => $allPermissionIds->keys()->all(),
            ],
            'support' => [
                'name' => 'Support',
                'description' => 'View merchants, websites, and billing',
                'permissions' => [
                    'dashboard.view',
                    'merchants.view',
                    'websites.view',
                    'licenses.view',
                    'billing.view',
                    'payments.view',
                    'sms.view',
                    'employees.view',
                    'wise.knowledge.edit',
                ],
            ],
            'billing-clerk' => [
                'name' => 'Billing Clerk',
                'description' => 'Review and approve payment requests',
                'permissions' => [
                    'dashboard.view',
                    'merchants.view',
                    'billing.view',
                    'billing.approve',
                    'payments.view',
                    'payments.approve',
                    'sms.view',
                    'sms.manage',
                ],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only platform access',
                'permissions' => [
                    'dashboard.view',
                    'merchants.view',
                    'websites.view',
                    'licenses.view',
                    'billing.view',
                    'payments.view',
                    'sms.view',
                    'employees.view',
                ],
            ],
        ];

        foreach ($platformRoles as $slug => $config) {
            $role = Role::updateOrCreate(
                ['slug' => $slug, 'scope' => 'platform'],
                [
                    'name' => $config['name'],
                    'description' => $config['description'],
                ]
            );

            $permissionIds = collect($config['permissions'])
                ->map(fn (string $perm) => $allPermissionIds[$perm] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        $merchantRoles = [
            'merchant-manager' => [
                'name' => 'Store Manager',
                'description' => 'Oversees store operations, billing, and team across assigned websites',
                'permissions' => ['websites.view', 'websites.manage', 'billing.view', 'billing.manage', 'employees.view', 'employees.manage'],
            ],
            'merchant-operator' => [
                'name' => 'Order Fulfillment',
                'description' => 'Processes orders, shipments, and courier bookings',
                'permissions' => ['websites.view', 'billing.view', 'employees.view'],
            ],
            'merchant-viewer' => [
                'name' => 'Customer Support',
                'description' => 'Handles customer inquiries, returns, and order status updates',
                'permissions' => ['websites.view', 'employees.view'],
            ],
            'merchant-inventory' => [
                'name' => 'Inventory & Catalog',
                'description' => 'Manages product listings, stock levels, and catalog updates',
                'permissions' => ['websites.view', 'employees.view'],
            ],
        ];

        foreach ($merchantRoles as $slug => $config) {
            $role = Role::updateOrCreate(
                ['slug' => $slug, 'scope' => 'merchant'],
                [
                    'name' => $config['name'],
                    'description' => $config['description'],
                ]
            );

            $permissionIds = collect($config['permissions'])
                ->map(fn (string $perm) => $allPermissionIds[$perm] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
