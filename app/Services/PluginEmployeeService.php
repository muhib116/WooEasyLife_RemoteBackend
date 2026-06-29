<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\Website;
use App\Traits\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PluginEmployeeService
{
    use Util;

    public function __construct(
        protected MerchantEmployeeService $employeeService,
        protected RbacService $rbac,
        protected DomainNormalizer $domainNormalizer
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
     * @return array{website_id: int|null, domain: string|null}
     */
    public function resolveCurrentWebsite(User $merchant, Request $request): array
    {
        $requestDomain = $this->getRequestDomain();

        if (! $requestDomain) {
            return [
                'website_id' => null,
                'domain' => null,
            ];
        }

        $website = Website::query()
            ->where('user_id', $merchant->id)
            ->get()
            ->first(fn (Website $record) => $this->domainNormalizer->matches(
                $record->domain,
                $requestDomain
            ));

        return [
            'website_id' => $website?->id ? (int) $website->id : null,
            'domain' => $website?->domain ?? $requestDomain,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForMerchant(User $merchant, ?int $websiteId = null): Collection
    {
        return $this->employeeService
            ->listForMerchant($merchant)
            ->map(fn (array $employee) => $this->formatEmployeeRecord($employee, $websiteId));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $employees
     * @return array<string, mixed>
     */
    public function websiteAssignmentSummary(Collection $employees, ?int $websiteId, ?string $domain): array
    {
        if (! $websiteId) {
            return [
                'website_id' => null,
                'domain' => $domain,
                'total' => 0,
                'employees' => [],
            ];
        }

        $assigned = $employees
            ->filter(fn (array $employee) => (bool) ($employee['assigned_to_website'] ?? false))
            ->values();

        return [
            'website_id' => $websiteId,
            'domain' => $domain,
            'total' => $assigned->count(),
            'employees' => $assigned
                ->map(fn (array $employee) => [
                    'id' => $employee['id'],
                    'name' => $employee['name'],
                    'photo_url' => $employee['photo_url'] ?? null,
                    'role' => $employee['role']['name'] ?? null,
                    'status' => (bool) ($employee['status'] ?? true),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listWebsitesForMerchant(User $merchant): Collection
    {
        return $this->employeeService
            ->assignableWebsitesForMerchant($merchant)
            ->map(fn (Website $website) => [
                'id' => $website->id,
                'domain' => $website->domain,
                'title' => $website->title,
            ]);
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
    public function formatEmployee(MerchantEmployee $employee, ?int $websiteId = null): array
    {
        $employee->loadMissing(['role:id,name,slug', 'websites:id']);

        return $this->formatEmployeeRecord([
            ...$employee->toArray(),
            'website_ids' => $employee->websites->pluck('id')->all(),
        ], $websiteId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $merchant, array $data, ?int $websiteId = null): array
    {
        $employee = $this->employeeService->create($merchant, $data);

        return $this->formatEmployee($employee, $websiteId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MerchantEmployee $employee, User $merchant, array $data, ?int $websiteId = null): array
    {
        $employee = $this->employeeService->update($employee, $merchant, $data);

        return $this->formatEmployee($employee, $websiteId);
    }

    public function delete(MerchantEmployee $employee, User $merchant): void
    {
        $this->employeeService->delete($employee, $merchant);
    }

    /**
     * Normalize website_ids when sent as a JSON string from multipart plugin forms.
     */
    public function normalizeWebsiteIdsInput(Request $request): void
    {
        if (! $request->has('website_ids')) {
            return;
        }

        $websiteIds = $request->input('website_ids');

        if (! is_string($websiteIds)) {
            return;
        }

        $decoded = json_decode($websiteIds, true);

        $request->merge([
            'website_ids' => is_array($decoded) ? $decoded : [],
        ]);
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
    private function formatEmployeeRecord(array $employee, ?int $websiteId = null): array
    {
        $role = $employee['role'] ?? null;
        $websiteIds = array_values($employee['website_ids'] ?? []);

        return [
            'id' => $employee['id'],
            'name' => $employee['name'],
            'phone' => $employee['phone'] ?? null,
            'email' => $employee['email'] ?? null,
            'address' => $employee['address'] ?? null,
            'photo_url' => $employee['photo_url'] ?? null,
            'status' => (bool) ($employee['status'] ?? true),
            'notes' => $employee['notes'] ?? null,
            'website_ids' => $websiteIds,
            'assigned_to_website' => $this->isAssignedToWebsite($websiteIds, $employee, $websiteId),
            'role' => $role ? [
                'id' => $role['id'] ?? null,
                'name' => $role['name'] ?? null,
                'slug' => $role['slug'] ?? null,
            ] : null,
        ];
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @param  array<string, mixed>  $employee
     */
    private function isAssignedToWebsite(array $websiteIds, array $employee, ?int $websiteId): bool
    {
        if (! $websiteId) {
            return false;
        }

        if ($websiteIds !== []) {
            return in_array($websiteId, $websiteIds, true);
        }

        $legacyWebsiteId = $employee['website_id'] ?? null;

        if ($legacyWebsiteId) {
            return (int) $legacyWebsiteId === $websiteId;
        }

        return true;
    }
}
