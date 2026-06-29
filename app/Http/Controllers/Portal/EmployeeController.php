<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\MerchantEmployee;
use App\Models\Website;
use App\Services\MerchantEmployeeService;
use App\Services\MerchantPortalContext;
use App\Services\RbacService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct(
        protected MerchantEmployeeService $employeeService,
        protected MerchantPortalContext $portalContext,
        protected RbacService $rbac
    ) {
    }

    public function index(Request $request)
    {
        $merchant = $this->portalContext->resolveMerchant($request->user());

        return Inertia::render('Portal/Employees/Index', [
            'employees' => $this->employeeService->listForMerchant($merchant),
            'roles' => $this->rbac->merchantRoles()->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ]),
            'websites' => Website::query()
                ->where('user_id', $merchant->id)
                ->orderBy('domain')
                ->get(['id', 'domain', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        $merchant = $this->portalContext->resolveMerchant($request->user());

        $request->validate($this->employeeRules());

        try {
            $this->employeeService->create($merchant, $this->employeePayload($request));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, $employeeId)
    {
        $merchant = $this->portalContext->resolveMerchant($request->user());
        $employee = MerchantEmployee::findOrFail($employeeId);

        $request->validate($this->employeeRules());

        try {
            $this->employeeService->update($employee, $merchant, $this->employeePayload($request));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Employee updated successfully.');
    }

    public function destroy(Request $request, $employeeId)
    {
        $merchant = $this->portalContext->resolveMerchant($request->user());
        $employee = MerchantEmployee::findOrFail($employeeId);

        $this->employeeService->delete($employee, $merchant);

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
