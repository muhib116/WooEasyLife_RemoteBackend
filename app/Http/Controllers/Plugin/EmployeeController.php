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

        $employees = $this->pluginEmployeeService->listForMerchant($merchant);

        return $this->successResponse([
            'employees' => $employees->values()->all(),
            'roles' => $this->pluginEmployeeService->listRoles()->values()->all(),
        ]);
    }

    public function show(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);

        return $this->successResponse([
            'employee' => $this->pluginEmployeeService->formatEmployee($employee),
        ]);
    }

    public function store(Request $request)
    {
        $merchant = $this->merchant();

        $validator = Validator::make(
            $request->all(),
            $this->pluginEmployeeService->validationRules()
        );

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $employee = $this->pluginEmployeeService->create(
                $merchant,
                $this->pluginEmployeeService->requestPayload($request)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        return $this->successResponse([
            'employee' => $employee,
        ], 'Employee created successfully.', 201);
    }

    public function update(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);

        $validator = Validator::make(
            $request->all(),
            $this->pluginEmployeeService->validationRules(updating: true)
        );

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $updated = $this->pluginEmployeeService->update(
                $employee,
                $merchant,
                $this->pluginEmployeeService->requestPayload($request)
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        }

        return $this->successResponse([
            'employee' => $updated,
        ], 'Employee updated successfully.');
    }

    public function destroy(Request $request, int $employeeId)
    {
        $merchant = $this->merchant();
        $employee = $this->pluginEmployeeService->findForMerchant($merchant, $employeeId);

        $this->pluginEmployeeService->delete($employee, $merchant);

        return $this->successResponse(null, 'Employee removed successfully.');
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
