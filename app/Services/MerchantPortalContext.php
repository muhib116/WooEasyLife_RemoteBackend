<?php

namespace App\Services;

use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Collection;

class MerchantPortalContext
{
    public function isMerchantOwner(User $user): bool
    {
        return $user->role === 'user';
    }

    public function isMerchantStaff(User $user): bool
    {
        return $user->role === 'merchant_staff';
    }

    public function canAccessPortal(User $user): bool
    {
        if (! $user->canAccessPlatform()) {
            return false;
        }

        if ($this->isMerchantOwner($user)) {
            return true;
        }

        if ($this->isMerchantStaff($user)) {
            $employee = $this->resolveEmployee($user);

            return $employee !== null && $employee->status;
        }

        return false;
    }

    public function resolveMerchant(User $user): User
    {
        if ($this->isMerchantOwner($user)) {
            return $user;
        }

        if ($this->isMerchantStaff($user) && $user->merchant_user_id) {
            $merchant = User::query()->find($user->merchant_user_id);

            if ($merchant && $merchant->role === 'user' && $merchant->canAccessPlatform()) {
                return $merchant;
            }
        }

        abort(403, 'Merchant account unavailable.');
    }

    public function resolveEmployee(User $user): ?MerchantEmployee
    {
        if (! $this->isMerchantStaff($user)) {
            return null;
        }

        return MerchantEmployee::query()
            ->with(['role.permissions', 'website:id,domain,title'])
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $websites
     * @return array<int, array<string, mixed>>
     */
    public function filterWebsitesForUser(User $user, array $websites): array
    {
        $employee = $this->resolveEmployee($user);

        if (! $employee?->website_id) {
            return $websites;
        }

        $scopedDomain = $employee->website?->domain;

        return collect($websites)
            ->filter(function (array $website) use ($employee, $scopedDomain) {
                if ($website['id'] ?? null) {
                    return (int) $website['id'] === (int) $employee->website_id;
                }

                return $scopedDomain && ($website['domain'] ?? null) === $scopedDomain;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sharePayload(User $user): ?array
    {
        if (! $this->canAccessPortal($user)) {
            return null;
        }

        $merchant = $this->resolveMerchant($user);
        $employee = $this->resolveEmployee($user);

        return [
            'is_owner' => $this->isMerchantOwner($user),
            'is_staff' => $this->isMerchantStaff($user),
            'merchant_id' => $merchant->id,
            'merchant_name' => $merchant->name,
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'role' => $employee->role?->name,
                'role_slug' => $employee->role?->slug,
                'website_id' => $employee->website_id,
                'website_domain' => $employee->website?->domain,
            ] : null,
        ];
    }
}
