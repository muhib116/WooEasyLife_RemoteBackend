<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\Website;
use App\Services\MerchantEmployeeService;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MerchantEmployeeController extends Controller
{
    public function __construct(
        protected MerchantEmployeeService $employeeService,
        protected RbacService $rbac
    ) {
    }

    public function index($userId)
    {
        $user = User::findOrFail($userId);

        if ($user->role !== 'user') {
            abort(404);
        }

        return Inertia::render('Users/Employees/Index', [
            'user' => $user,
            'employees' => $this->employeeService->listForMerchant($user),
            'roles' => $this->rbac->merchantRoles()->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ]),
            'websites' => Website::query()
                ->where('user_id', $user->id)
                ->orderBy('domain')
                ->get(['id', 'domain', 'title']),
        ]);
    }

    public function store(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role_id' => 'required|integer',
            'website_id' => 'nullable|integer',
            'status' => 'boolean',
            'notes' => 'nullable|string',
            'grant_portal_access' => 'boolean',
            'portal_password' => 'nullable|string|min:8',
        ]);

        try {
            $this->employeeService->create($user, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, $userId, $employeeId)
    {
        $user = User::findOrFail($userId);
        $employee = MerchantEmployee::findOrFail($employeeId);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role_id' => 'required|integer',
            'website_id' => 'nullable|integer',
            'status' => 'boolean',
            'notes' => 'nullable|string',
            'grant_portal_access' => 'boolean',
            'portal_password' => 'nullable|string|min:8',
        ]);

        try {
            $this->employeeService->update($employee, $user, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Employee updated successfully.');
    }

    public function destroy($userId, $employeeId)
    {
        $user = User::findOrFail($userId);
        $employee = MerchantEmployee::findOrFail($employeeId);

        $this->employeeService->delete($employee, $user);

        return back()->with('success', 'Employee removed successfully.');
    }
}
