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
        protected WebsiteSyncService $websiteSync
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(User $user, PackageHub $package, array $data): UserPackage
    {
        $domain = $this->domainNormalizer->normalize($data['domain'] ?? null);
        if (! $domain) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

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
}
