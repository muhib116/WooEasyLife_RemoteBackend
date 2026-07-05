<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RbacService
{
    /**
     * Permissions granted to merchant account owners in the portal.
     *
     * @var array<int, string>
     */
    public const MERCHANT_OWNER_PERMISSIONS = [
        'websites.view',
        'websites.manage',
        'billing.view',
        'billing.manage',
        'employees.view',
        'employees.manage',
        'sms.view',
    ];

    public function isSuperAdmin(User $user): bool
    {
        return $user->role === 'admin' && ! $user->admin_role_id;
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if (in_array($user->role, ['user', 'merchant_staff'], true)) {
            return in_array($permission, $this->merchantPermissionSlugsFor($user), true);
        }

        if ($user->role === 'admin') {
            if ($this->isSuperAdmin($user)) {
                return true;
            }

            return in_array($permission, $this->permissionSlugsFor($user), true);
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function merchantPermissionSlugsFor(User $user): array
    {
        if ($user->role === 'user') {
            return self::MERCHANT_OWNER_PERMISSIONS;
        }

        if ($user->role === 'merchant_staff') {
            $employee = MerchantEmployee::query()
                ->with('role.permissions')
                ->where('user_id', $user->id)
                ->where('status', true)
                ->first();

            return $employee?->role?->permissions
                ->pluck('slug')
                ->sort()
                ->values()
                ->all() ?? [];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    public function permissionSlugsFor(User $user): array
    {
        if ($user->role === 'admin') {
            if ($this->isSuperAdmin($user)) {
                return Permission::query()->orderBy('slug')->pluck('slug')->all();
            }

            $role = $user->adminRole?->loadMissing('permissions');

            return $role?->permissions->pluck('slug')->sort()->values()->all() ?? [];
        }

        return [];
    }

    /**
     * @return Collection<int, Role>
     */
    public function platformRoles(): Collection
    {
        return Role::query()
            ->where('scope', 'platform')
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Role>
     */
    public function merchantRoles(): Collection
    {
        return Role::query()
            ->where('scope', 'merchant')
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    public function assignAdminRole(User $admin, ?Role $role): User
    {
        if ($admin->role !== 'admin') {
            abort(422, 'Only platform admin accounts can be assigned admin roles.');
        }

        if ($role && ! $role->isPlatformScope()) {
            abort(422, 'Invalid platform role.');
        }

        $admin->update(['admin_role_id' => $role?->id]);

        return $admin->fresh();
    }

    public function syncRolePermissions(Role $role, array $permissionIds): Role
    {
        $permissions = Permission::query()->whereIn('id', $permissionIds)->pluck('id');
        $role->permissions()->sync($permissions);

        Cache::forget('rbac.permissions');

        return $role->fresh('permissions');
    }
}
