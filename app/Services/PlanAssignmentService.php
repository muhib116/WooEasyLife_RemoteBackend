<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PlanAssignmentService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected WebsiteSyncService $websiteSync,
        protected PackagePlanResolver $planResolver,
        protected DomainAvailabilityService $domainAvailability
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(User $user, PackageHub $package, array $data): UserPackage
    {
        if ($this->planResolver->isCatalog($package)) {
            return $this->assignCatalog($user, $package, $data);
        }

        return $this->assignLegacy($user, $package, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assignLegacy(User $user, PackageHub $package, array $data): UserPackage
    {
        $domain = $this->domainNormalizer->normalize($data['domain'] ?? null);
        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $this->domainAvailability->assertAvailableForUser($user, $domain, forAdmin: true);

        $limit = (int) ($data['limit'] ?? 0);
        if ($limit <= 0) {
            throw ValidationException::withMessages([
                'limit' => 'Order limit is required',
            ]);
        }

        $userPackage = UserPackage::create([
            'title' => $package->title,
            'description' => $package->description,
            'domain' => $domain,
            'user_id' => $user->id,
            'package_hub_id' => $package->id,
            'plan_type' => 'legacy',
            'total_order_can_handle' => $limit,
            'remaining_order' => $limit,
            'total_order_handled' => 0,
            'per_order_rate' => $package->per_order_rate,
            'transaction_method' => $data['transaction_method'] ?? $package->transaction_method ?? 'Cash',
            'transaction_number' => $data['transaction_number'] ?? $package->transaction_number,
            'transaction_id' => $data['transaction_id'] ?? $package->transaction_id,
            'total_cost' => $package->per_order_rate * $limit,
            'transaction_charge' => (float) ($data['transaction_charge'] ?? 0),
            'is_active' => true,
            'note' => $data['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->websiteSync->linkUserPackage($userPackage);

        return $userPackage->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assignCatalog(User $user, PackageHub $package, array $data): UserPackage
    {
        $domain = $this->domainNormalizer->normalize($data['domain'] ?? null);
        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $this->domainAvailability->assertAvailableForUser($user, $domain, forAdmin: true);

        $tokens = (int) ($package->order_rate_token ?? 0);
        if ($tokens <= 0) {
            throw ValidationException::withMessages([
                'package_id' => 'This catalog plan has no token quota configured.',
            ]);
        }

        $userPackage = UserPackage::create([
            'title' => $package->title,
            'description' => $package->description,
            'domain' => $domain,
            'user_id' => $user->id,
            'package_hub_id' => $package->id,
            'plan_type' => 'catalog',
            'order_rate_token' => $tokens,
            'package_duration' => $package->package_duration,
            'features' => $package->features,
            'total_order_can_handle' => $tokens,
            'remaining_order' => $tokens,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'transaction_method' => $data['transaction_method'] ?? $package->transaction_method ?? 'Cash',
            'transaction_number' => $data['transaction_number'] ?? $package->transaction_number,
            'transaction_id' => $data['transaction_id'] ?? $package->transaction_id,
            'total_cost' => (float) ($package->package_price ?? 0),
            'transaction_charge' => (float) ($data['transaction_charge'] ?? 0),
            'is_active' => true,
            'expires_at' => $this->planResolver->expiresAt($package),
            'note' => $data['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->websiteSync->linkUserPackage($userPackage);

        return $userPackage->fresh();
    }
}
