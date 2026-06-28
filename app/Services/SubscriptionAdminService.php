<?php

namespace App\Services;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionAdminService
{
    public function __construct(
        protected PackagePlanResolver $planResolver,
        protected PlanAssignmentService $planAssignment,
        protected WebsiteSyncService $websiteSync
    ) {
    }

    public function renewCatalog(UserPackage $userPackage): UserPackage
    {
        if (($userPackage->plan_type ?? 'legacy') !== 'catalog') {
            throw ValidationException::withMessages([
                'subscription' => 'Legacy plans renew through Billing — submit a payment request to add order quota.',
            ]);
        }

        $packageHub = PackageHub::findOrFail($userPackage->package_hub_id);

        if (! $this->planResolver->isCatalog($packageHub)) {
            throw ValidationException::withMessages([
                'subscription' => 'This subscription is not linked to a catalog plan.',
            ]);
        }

        return $this->applyCatalogRenewal($userPackage, $packageHub);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function changePlan(User $user, UserPackage $existing, PackageHub $newHub, array $data): UserPackage
    {
        $existingIsCatalog = ($existing->plan_type ?? 'legacy') === 'catalog';
        $newIsCatalog = $this->planResolver->isCatalog($newHub);

        if ($existingIsCatalog && $newIsCatalog) {
            return $this->applyCatalogRenewal($existing, $newHub);
        }

        return DB::transaction(function () use ($user, $existing, $newHub, $data) {
            $existing->update([
                'is_active' => false,
                'updated_by' => Auth::id(),
            ]);

            return $this->planAssignment->assign($user, $newHub, [
                'domain' => $data['domain'] ?? $existing->domain,
                'limit' => $data['limit'] ?? null,
                'transaction_method' => $data['transaction_method'] ?? 'Cash',
                'transaction_number' => $data['transaction_number'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'transaction_charge' => $data['transaction_charge'] ?? 0,
                'note' => $data['note'] ?? null,
            ]);
        });
    }

    private function applyCatalogRenewal(UserPackage $userPackage, PackageHub $packageHub): UserPackage
    {
        $tokens = (int) ($packageHub->order_rate_token ?? 0);
        if ($tokens <= 0) {
            throw ValidationException::withMessages([
                'package_id' => 'This catalog plan has no token quota configured.',
            ]);
        }

        $userPackage->update([
            'title' => $packageHub->title,
            'package_hub_id' => $packageHub->id,
            'plan_type' => 'catalog',
            'order_rate_token' => $tokens,
            'package_duration' => $packageHub->package_duration,
            'features' => $packageHub->features,
            'total_order_can_handle' => $tokens,
            'remaining_order' => $tokens,
            'total_order_handled' => 0,
            'total_cost' => (float) ($packageHub->package_price ?? 0),
            'expires_at' => $this->planResolver->expiresAt($packageHub),
            'is_active' => true,
            'updated_by' => Auth::id(),
        ]);

        $this->websiteSync->linkUserPackage($userPackage->fresh());

        return $userPackage->fresh();
    }
}
