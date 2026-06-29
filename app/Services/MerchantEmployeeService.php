<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MerchantEmployeeService
{
    public function __construct(
        protected RbacService $rbac,
        protected WebsiteSyncService $websiteSyncService
    ) {
    }

    /**
     * @return Collection<int, Website>
     */
    public function assignableWebsitesForMerchant(User $merchant): Collection
    {
        $this->websiteSyncService->backfillUser($merchant);

        return Website::query()
            ->where('user_id', $merchant->id)
            ->orderBy('domain')
            ->get(['id', 'domain', 'title']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForMerchant(User $merchant): Collection
    {
        return MerchantEmployee::query()
            ->with([
                'role:id,name,slug,description',
                'website:id,domain,title',
                'websites:id,domain,title',
                'portalUser:id,email,status',
            ])
            ->where('merchant_user_id', $merchant->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (MerchantEmployee $employee) {
                return [
                    ...$employee->toArray(),
                    'website_ids' => $employee->websites->pluck('id')->all(),
                    'has_portal_access' => (bool) $employee->user_id,
                    'portal_email' => $employee->portalUser?->email,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $merchant, array $data): MerchantEmployee
    {
        $role = $this->resolveMerchantRole($data['role_id'] ?? null);
        $websiteIds = $this->resolveWebsiteIds($merchant, $data);

        $employee = MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $role->id,
            'website_id' => $websiteIds[0] ?? null,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => (bool) ($data['status'] ?? true),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->syncWebsites($employee, $merchant, $websiteIds);
        $this->storePhoto($employee, $data['photo'] ?? null);

        if ($this->shouldGrantPortalAccess($data)) {
            $this->syncPortalAccount($employee->fresh(), $merchant, (string) $data['portal_password']);
        }

        return $employee->fresh(['role', 'website', 'websites', 'portalUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MerchantEmployee $employee, User $merchant, array $data): MerchantEmployee
    {
        $this->assertBelongsToMerchant($employee, $merchant);

        $role = $this->resolveMerchantRole($data['role_id'] ?? $employee->role_id);
        $websiteIds = $this->resolveWebsiteIds($merchant, $data, $employee);
        $status = array_key_exists('status', $data) ? (bool) $data['status'] : $employee->status;

        $employee->update([
            'role_id' => $role->id,
            'website_id' => $websiteIds[0] ?? null,
            'name' => $data['name'] ?? $employee->name,
            'email' => array_key_exists('email', $data) ? $data['email'] : $employee->email,
            'phone' => $data['phone'] ?? $employee->phone,
            'address' => array_key_exists('address', $data) ? $data['address'] : $employee->address,
            'status' => $status,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $employee->notes,
            'updated_by' => Auth::id(),
        ]);

        $this->syncWebsites($employee, $merchant, $websiteIds);
        $this->updatePhoto($employee, $data);

        if ($this->shouldGrantPortalAccess($data)) {
            $this->syncPortalAccount(
                $employee->fresh(),
                $merchant,
                (string) ($data['portal_password'] ?? '')
            );
        } elseif (! empty($data['grant_portal_access']) && $employee->user_id) {
            $this->syncPortalAccount($employee->fresh(), $merchant, '');
        } elseif (array_key_exists('grant_portal_access', $data) && ! $data['grant_portal_access']) {
            $this->revokePortalAccount($employee->fresh());
        } elseif (! $status) {
            $this->revokePortalAccount($employee->fresh(), deactivateOnly: true);
        } elseif ($status && $employee->user_id) {
            $this->syncPortalAccount($employee->fresh(), $merchant, '');
        }

        return $employee->fresh(['role', 'website', 'websites', 'portalUser']);
    }

    public function delete(MerchantEmployee $employee, User $merchant): void
    {
        $this->assertBelongsToMerchant($employee, $merchant);
        $this->revokePortalAccount($employee);
        $this->deletePhoto($employee);
        $employee->websites()->detach();
        $employee->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function resolveWebsiteIds(User $merchant, array $data, ?MerchantEmployee $employee = null): array
    {
        if (array_key_exists('website_ids', $data)) {
            $websiteIds = collect($data['website_ids'] ?? [])
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } elseif (array_key_exists('website_id', $data)) {
            $websiteIds = $data['website_id'] ? [(int) $data['website_id']] : [];
        } elseif ($employee) {
            $employee->loadMissing('websites');

            if ($employee->websites->isNotEmpty()) {
                $websiteIds = $employee->websites->pluck('id')->all();
            } else {
                $websiteIds = $employee->website_id ? [(int) $employee->website_id] : [];
            }
        } else {
            $websiteIds = [];
        }

        if ($websiteIds === []) {
            return [];
        }

        $validIds = Website::query()
            ->where('user_id', $merchant->id)
            ->whereIn('id', $websiteIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count($websiteIds)) {
            throw ValidationException::withMessages([
                'website_ids' => 'One or more selected websites are invalid for this merchant.',
            ]);
        }

        return $validIds;
    }

    /**
     * @param  array<int, int>  $websiteIds
     */
    private function syncWebsites(MerchantEmployee $employee, User $merchant, array $websiteIds): void
    {
        if ($websiteIds === []) {
            $employee->websites()->sync([]);

            return;
        }

        $employee->websites()->sync(
            collect($websiteIds)
                ->mapWithKeys(fn (int $websiteId) => [
                    $websiteId => [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ])
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updatePhoto(MerchantEmployee $employee, array $data): void
    {
        if (($data['remove_photo'] ?? false) === true) {
            $this->deletePhoto($employee);
            $employee->update(['photo' => null]);

            return;
        }

        if (($data['photo'] ?? null) instanceof UploadedFile) {
            $this->deletePhoto($employee);
            $this->storePhoto($employee, $data['photo']);
        }
    }

    private function storePhoto(MerchantEmployee $employee, mixed $photo): void
    {
        if (! $photo instanceof UploadedFile) {
            return;
        }

        $path = $photo->store('employees', 'public');
        $employee->update(['photo' => $path]);
    }

    private function deletePhoto(MerchantEmployee $employee): void
    {
        if (! $employee->photo) {
            return;
        }

        Storage::disk('public')->delete($employee->photo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldGrantPortalAccess(array $data): bool
    {
        return ! empty($data['grant_portal_access'])
            && ! empty($data['email'])
            && ! empty($data['portal_password']);
    }

    private function syncPortalAccount(
        MerchantEmployee $employee,
        User $merchant,
        string $password
    ): User {
        if ($password === '') {
            if ($employee->user_id) {
                $portalUser = User::withTrashed()->find($employee->user_id);

                if ($portalUser) {
                    $portalUser->fill([
                        'name' => $employee->name,
                        'email' => trim((string) $employee->email),
                        'phone' => $employee->phone,
                        'role' => 'merchant_staff',
                        'merchant_user_id' => $merchant->id,
                        'status' => $employee->status,
                    ]);
                    $portalUser->save();

                    return $portalUser;
                }
            }

            throw ValidationException::withMessages([
                'portal_password' => 'Portal password is required when granting portal access.',
            ]);
        }

        if (strlen($password) < 8) {
            throw ValidationException::withMessages([
                'portal_password' => 'Portal password must be at least 8 characters.',
            ]);
        }

        $email = trim((string) $employee->email);

        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Employee email is required for portal access.',
            ]);
        }

        $existingUserQuery = User::withTrashed()->where('email', $email);

        if ($employee->user_id) {
            $existingUserQuery->where('id', '!=', $employee->user_id);
        }

        if ($existingUserQuery->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used by another account.',
            ]);
        }

        $portalUser = $employee->user_id
            ? User::withTrashed()->find($employee->user_id)
            : null;

        if ($portalUser?->trashed()) {
            $portalUser->restore();
        }

        if (! $portalUser) {
            $portalUser = new User();
        }

        $portalUser->fill([
            'name' => $employee->name,
            'email' => $email,
            'phone' => $employee->phone,
            'password' => Hash::make($password),
            'role' => 'merchant_staff',
            'merchant_user_id' => $merchant->id,
            'status' => $employee->status,
        ]);
        $portalUser->save();

        $employee->update(['user_id' => $portalUser->id]);

        return $portalUser;
    }

    private function revokePortalAccount(
        MerchantEmployee $employee,
        bool $deactivateOnly = false
    ): void {
        if (! $employee->user_id) {
            return;
        }

        $portalUser = User::query()->find($employee->user_id);

        if (! $portalUser) {
            $employee->update(['user_id' => null]);

            return;
        }

        $portalUser->revokePlatformAccess();
        $portalUser->update(['status' => false]);

        if (! $deactivateOnly) {
            $portalUser->delete();
            $employee->update(['user_id' => null]);
        }
    }

    private function resolveMerchantRole(mixed $roleId): Role
    {
        $role = Role::query()
            ->where('id', $roleId)
            ->where('scope', 'merchant')
            ->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role_id' => 'Invalid employee role.',
            ]);
        }

        return $role;
    }

    private function assertBelongsToMerchant(MerchantEmployee $employee, User $merchant): void
    {
        if ((int) $employee->merchant_user_id !== (int) $merchant->id) {
            abort(404);
        }
    }
}
