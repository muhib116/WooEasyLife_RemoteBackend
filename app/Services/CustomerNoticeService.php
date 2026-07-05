<?php

namespace App\Services;

use App\Models\CustomerNotice;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomerNoticeService
{
    private const CACHE_KEY = 'customer_notices_active';

    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * All currently-live notices (cached briefly, merchant-agnostic).
     *
     * @return Collection<int, CustomerNotice>
     */
    public function liveNotices(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinute(), function () {
            return CustomerNotice::query()
                ->active()
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->get();
        });
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Notices a specific merchant/domain should currently see.
     *
     * @return Collection<int, CustomerNotice>
     */
    public function activeNoticesFor(User $user, ?string $domain): Collection
    {
        $segments = $this->segmentsFor($user, $domain);
        $targets = array_merge(['all'], $segments);

        return $this->liveNotices()
            ->filter(fn (CustomerNotice $notice) => in_array($notice->audience, $targets, true))
            ->values();
    }

    /**
     * Merchant segments derived from live subscription state for a domain.
     *
     * @return array<int, string>
     */
    public function segmentsFor(User $user, ?string $domain): array
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->matches(
                $package->domain,
                $domain
            ));

        $latest = $packages->sortByDesc('id')->first();

        if (! $latest) {
            return [];
        }

        $segments = [];
        $expiresAt = $latest->expires_at;
        $isExpired = $expiresAt && now()->greaterThan($expiresAt);

        if ($isExpired) {
            $daysSinceExpiry = (int) $expiresAt->diffInDays(now());
            $recentWindow = (int) config('subscription.recent_expired_days', 30);

            if ($daysSinceExpiry <= $recentWindow) {
                $segments[] = 'recent_expired';
            }

            if (! $this->hasPendingRenewal($user, $domain)) {
                $segments[] = 'not_renewed';
            }

            return $segments;
        }

        $remainingOrders = (int) $packages->where('is_active', true)->sum('remaining_order');

        if ($remainingOrders > 0) {
            $segments[] = 'active_subscribers';
        }

        if ($expiresAt) {
            $daysUntilExpiry = (int) now()->diffInDays($expiresAt, false);
            $expiringWindow = (int) config('subscription.subscription_expiring_days', 7);

            if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= $expiringWindow) {
                $segments[] = 'expiring_soon';
            }
        }

        return $segments;
    }

    private function hasPendingRenewal(User $user, ?string $domain): bool
    {
        return PackagePaymentRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->contains(fn (PackagePaymentRequest $request) => $this->domainNormalizer->matches(
                $request->domain,
                $domain
            ));
    }
}
