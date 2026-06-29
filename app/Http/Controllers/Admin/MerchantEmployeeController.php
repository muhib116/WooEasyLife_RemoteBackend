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

        $user->loadCount(['websites', 'merchantEmployees']);

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

        $request->validate($this->employeeRules());

        try {
            $this->employeeService->create($user, $this->employeePayload($request));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, $userId, $employeeId)
    {
        $user = User::findOrFail($userId);
        $employee = MerchantEmployee::findOrFail($employeeId);

        $request->validate($this->employeeRules());

        try {
            $this->employeeService->update($employee, $user, $this->employeePayload($request));
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

    /**
     * @return array<string, mixed>
     */
    private function employeeRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:50',
            'address' => 'nullable|string|max:1000',
            'role_id' => 'required|integer',
            'website_ids' => 'nullable|array',
            'website_ids.*' => 'integer',
            'status' => 'sometimes|boolean',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'remove_photo' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Request $request): array
    {
        $payload = $request->except(['photo', 'remove_photo', 'status', '_method', 'id']);

        $payload['photo'] = $request->file('photo');

        if ($request->has('remove_photo')) {
            $payload['remove_photo'] = $request->boolean('remove_photo');
        }

        if ($request->has('status')) {
            $payload['status'] = $request->boolean('status');
        }

        return $payload;
    }
}
