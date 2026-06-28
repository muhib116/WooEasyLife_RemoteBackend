<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Collection;

class SubscriptionExpiryService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * @return array{tokens_disabled: int, plans_deactivated: int}
     */
    public function apply(): array
    {
        $result = [
            'tokens_disabled' => 0,
            'plans_deactivated' => 0,
        ];

        if (config('subscription.expiry.auto_disable_tokens', true)) {
            $result['tokens_disabled'] = $this->disableExpiredTokens();
        }

        if (config('subscription.expiry.auto_deactivate_plans', true)) {
            $result['plans_deactivated'] = $this->deactivateExpiredOrExhaustedPlans();
        }

        return $result;
    }

    private function disableExpiredTokens(): int
    {
        return AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('status', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => false]);
    }

    private function deactivateExpiredOrExhaustedPlans(): int
    {
        $count = 0;

        UserPackage::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('remaining_order', '<=', 0)
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('expires_at')
                            ->where('expires_at', '<', now());
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $packages) use (&$count) {
                foreach ($packages as $package) {
                    $package->update([
                        'is_active' => false,
                        'remaining_order' => max(0, (int) $package->remaining_order),
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
