<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PluginEmployeeService
{
    public function __construct(
        protected MerchantEmployeeService $employeeService,
        protected RbacService $rbac
    ) {
    }

    public function resolveMerchant(User $user): User
    {
        if ($user->role !== 'user') {
            abort(403, 'Employee management is only available for merchant accounts.');
        }

        return $user;
    }

    public function findForMerchant(User $merchant, int $employeeId): MerchantEmployee
    {
        $employee = MerchantEmployee::query()
            ->where('merchant_user_id', $merchant->id)
            ->where('id', $employeeId)
            ->first();

        if (! $employee) {
            abort(404, 'Employee not found.');
        }

        return $employee;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForMerchant(User $merchant): Collection
    {
        return $this->employeeService
            ->listForMerchant($merchant)
            ->map(fn (array $employee) => $this->formatEmployeeRecord($employee));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listRoles(): Collection
    {
        return $this->rbac->merchantRoles()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEmployee(MerchantEmployee $employee): array
    {
        $employee->loadMissing(['role:id,name,slug', 'websites:id']);

        return $this->formatEmployeeRecord([
            ...$employee->toArray(),
            'website_ids' => $employee->websites->pluck('id')->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $merchant, array $data): array
    {
        $employee = $this->employeeService->create($merchant, $data);

        return $this->formatEmployee($employee);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MerchantEmployee $employee, User $merchant, array $data): array
    {
        $employee = $this->employeeService->update($employee, $merchant, $data);

        return $this->formatEmployee($employee);
    }

    public function delete(MerchantEmployee $employee, User $merchant): void
    {
        $this->employeeService->delete($employee, $merchant);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(Request $request): array
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

    /**
     * @return array<string, mixed>
     */
    public function validationRules(bool $updating = false): array
    {
        return [
            'name' => ($updating ? 'sometimes|' : '').'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => ($updating ? 'sometimes|' : '').'required|string|max:50',
            'address' => 'nullable|string|max:1000',
            'role_id' => ($updating ? 'sometimes|' : '').'required|integer',
            'website_ids' => 'nullable|array',
            'website_ids.*' => 'integer',
            'status' => 'sometimes|boolean',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'remove_photo' => 'sometimes|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $employee
     * @return array<string, mixed>
     */
    private function formatEmployeeRecord(array $employee): array
    {
        $role = $employee['role'] ?? null;

        return [
            'id' => $employee['id'],
            'name' => $employee['name'],
            'phone' => $employee['phone'] ?? null,
            'email' => $employee['email'] ?? null,
            'address' => $employee['address'] ?? null,
            'photo_url' => $employee['photo_url'] ?? null,
            'status' => (bool) ($employee['status'] ?? true),
            'notes' => $employee['notes'] ?? null,
            'website_ids' => array_values($employee['website_ids'] ?? []),
            'role' => $role ? [
                'id' => $role['id'] ?? null,
                'name' => $role['name'] ?? null,
                'slug' => $role['slug'] ?? null,
            ] : null,
        ];
    }
}
