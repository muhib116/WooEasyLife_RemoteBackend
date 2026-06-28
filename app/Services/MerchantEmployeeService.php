<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MerchantEmployeeService
{
    public function __construct(
        protected RbacService $rbac
    ) {
    }

    /**
     * @return Collection<int, MerchantEmployee>
     */
    public function listForMerchant(User $merchant): Collection
    {
        return MerchantEmployee::query()
            ->with(['role:id,name,slug', 'website:id,domain,title', 'portalUser:id,email,status'])
            ->where('merchant_user_id', $merchant->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (MerchantEmployee $employee) {
                return [
                    ...$employee->toArray(),
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
        $website = $this->resolveWebsite($merchant, $data['website_id'] ?? null);

        $employee = MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $role->id,
            'website_id' => $website?->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => (bool) ($data['status'] ?? true),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        if ($this->shouldGrantPortalAccess($data)) {
            $this->syncPortalAccount($employee, $merchant, (string) $data['portal_password']);
        }

        return $employee->fresh(['role', 'website', 'portalUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MerchantEmployee $employee, User $merchant, array $data): MerchantEmployee
    {
        $this->assertBelongsToMerchant($employee, $merchant);

        $role = $this->resolveMerchantRole($data['role_id'] ?? $employee->role_id);
        $website = $this->resolveWebsite($merchant, $data['website_id'] ?? $employee->website_id);
        $status = array_key_exists('status', $data) ? (bool) $data['status'] : $employee->status;

        $employee->update([
            'role_id' => $role->id,
            'website_id' => $website?->id,
            'name' => $data['name'] ?? $employee->name,
            'email' => $data['email'] ?? $employee->email,
            'phone' => $data['phone'] ?? $employee->phone,
            'status' => $status,
            'notes' => $data['notes'] ?? $employee->notes,
            'updated_by' => Auth::id(),
        ]);

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
        }

        return $employee->fresh(['role', 'website', 'portalUser']);
    }

    public function delete(MerchantEmployee $employee, User $merchant): void
    {
        $this->assertBelongsToMerchant($employee, $merchant);
        $this->revokePortalAccount($employee);
        $employee->delete();
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

    private function resolveWebsite(User $merchant, mixed $websiteId): ?Website
    {
        if (! $websiteId) {
            return null;
        }

        $website = Website::query()
            ->where('id', $websiteId)
            ->where('user_id', $merchant->id)
            ->first();

        if (! $website) {
            throw ValidationException::withMessages([
                'website_id' => 'Invalid website for this merchant.',
            ]);
        }

        return $website;
    }

    private function assertBelongsToMerchant(MerchantEmployee $employee, User $merchant): void
    {
        if ((int) $employee->merchant_user_id !== (int) $merchant->id) {
            abort(404);
        }
    }
}
