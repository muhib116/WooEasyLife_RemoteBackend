<?php

namespace App\Http\Controllers\Plugin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PluginEmployeeService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected PluginEmployeeService $pluginEmployeeService
    ) {
    }

    public function index(Request $request)
    {
        $merchant = $this->merchant();
        $website = $this->pluginEmployeeService->resolveCurrentWebsite($merchant, $request);
        $employees = $this->pluginEmployeeService->listForMerchant($merchant, $website['website_id']);

        return $this->successResponse([
            'employees' => $employees->values()->all(),
            'roles' => $this->pluginEmployeeService->listRoles()->values()->all(),
            'websites' => $this->pluginEmployeeService->listWebsitesForMerchant($merchant)->values()->all(),
            'current_website_id' => $website['website_id'],
            'website_assignment' => $this->pluginEmployeeService->websiteAssignmentSummary(
                $employees,
                $website['website_id'],
                $website['domain']
            ),
        ]);
    }

    public function show(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);
        $website = $this->pluginEmployeeService->resolveCurrentWebsite($merchant, $request);

        return $this->successResponse([
            'employee' => $this->pluginEmployeeService->formatEmployee($employee, $website['website_id']),
        ]);
    }

    public function store(Request $request)
    {
        $merchant = $this->merchant();

        $this->pluginEmployeeService->normalizeWebsiteIdsInput($request);

        $validator = Validator::make(
            $request->all(),
            $this->pluginEmployeeService->validationRules()
        );

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $website = $this->pluginEmployeeService->resolveCurrentWebsite($merchant, $request);
            $result = $this->pluginEmployeeService->create(
                $merchant,
                $this->pluginEmployeeService->requestPayload($request),
                $website['website_id']
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        return $this->successResponse([
            'employee' => $result['employee'],
            'store_sync' => $result['store_sync'] ?? [],
        ], 'Employee created successfully.', 201);
    }

    public function update(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);

        $this->pluginEmployeeService->normalizeWebsiteIdsInput($request);

        $validator = Validator::make(
            $request->all(),
            $this->pluginEmployeeService->validationRules(updating: true)
        );

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $website = $this->pluginEmployeeService->resolveCurrentWebsite($merchant, $request);
            $result = $this->pluginEmployeeService->update(
                $employee,
                $merchant,
                $this->pluginEmployeeService->requestPayload($request),
                $website['website_id']
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        return $this->successResponse([
            'employee' => $result['employee'],
            'store_sync' => $result['store_sync'] ?? [],
        ], 'Employee updated successfully.');
    }

    public function destroy(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);

        $storeSync = $this->pluginEmployeeService->delete($employee, $merchant);

        return $this->successResponse($storeSync, 'Employee removed successfully.');
    }

    private function merchant(): User
    {
        $user = User::find(Auth::id());

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        return $this->pluginEmployeeService->resolveMerchant($user);
    }
}
