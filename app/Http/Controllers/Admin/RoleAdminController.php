<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleAdminController extends Controller
{
    public function __construct(
        protected RbacService $rbac
    ) {
    }

    public function index()
    {
        $roles = $this->rbac->platformRoles()->map(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('slug')->values(),
                'admin_count' => User::where('admin_role_id', $role->id)->count(),
            ];
        });

        $permissions = Permission::query()->orderBy('group')->orderBy('slug')->get();

        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'admin_role_id']);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'admins' => $admins,
            'canManageRoles' => auth()->user()?->hasPermission('roles.manage') ?? false,
        ]);
    }

    public function assignAdminRole(Request $request, $userId)
    {
        $request->validate([
            'admin_role_id' => 'nullable|integer|exists:roles,id',
        ]);

        $admin = User::where('role', 'admin')->findOrFail($userId);
        $role = $request->admin_role_id
            ? Role::where('scope', 'platform')->findOrFail($request->admin_role_id)
            : null;

        $this->rbac->assignAdminRole($admin, $role);

        return back()->with('success', 'Admin role updated.');
    }

    public function syncPermissions(Request $request, $roleId)
    {
        $request->validate([
            'permission_ids' => 'array',
            'permission_ids.*' => 'integer|exists:permissions,id',
        ]);

        $role = Role::where('scope', 'platform')->findOrFail($roleId);

        if ($role->slug === 'super-admin') {
            return back()->with('error', 'Super Admin permissions cannot be edited.');
        }

        $this->rbac->syncRolePermissions($role, $request->input('permission_ids', []));

        return back()->with('success', 'Role permissions updated.');
    }
}
