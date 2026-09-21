<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Collection;

class MerchantOpsSummaryService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Add domains + attention onto each user for the admin merchant list.
     * Existing user fields are left unchanged.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function appendToUsers(Collection $users): Collection
    {
        $merchantIds = $users
            ->filter(fn (User $user) => $user->role === 'user')
            ->pluck('id')
            ->filter()
            ->values();

        if ($merchantIds->isEmpty()) {
            return $users->map(function (User $user) {
                $user->setAttribute('domains', []);
                $user->setAttribute('attention', null);

                return $user;
            });
        }

        $packages = UserPackage::query()
            ->whereIn('user_id', $merchantIds)
            ->get(['id', 'user_id', 'domain', 'website_id', 'expires_at', 'remaining_order', 'is_active'])
            ->groupBy('user_id');

        $websites = Website::query()
            ->whereIn('user_id', $merchantIds)
            ->get(['id', 'user_id', 'domain'])
            ->groupBy('user_id');

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $merchantIds)
            ->get(['id', 'tokenable_id', 'domain', 'website_id', 'expires_at', 'status'])
            ->groupBy('tokenable_id');

        return $users->map(function (User $user) use ($packages, $websites, $tokens) {
            $summary = $this->summarize(
                $user,
                $packages->get($user->id, collect()),
                $websites->get($user->id, collect()),
                $tokens->get($user->id, collect()),
            );

            $user->setAttribute('domains', $summary['domains']);
            $user->setAttribute('attention', $summary['attention']);

            return $user;
        });
    }

    /**
     * @param  Collection<int, UserPackage>  $packages
     * @param  Collection<int, Website>  $websites
     * @param  Collection<int, AccessToken>  $tokens
     * @return array{domains: array<int, string>, attention: array<string, mixed>|null}
     */
    public function summarize(User $user, Collection $packages, Collection $websites, Collection $tokens): array
    {
        if ($user->role !== 'user') {
            return [
                'domains' => [],
                'attention' => null,
            ];
        }

        $domains = collect()
            ->merge($websites->map(fn (Website $website) => $this->domainNormalizer->normalize($website->domain)))
            ->merge($packages->map(fn (UserPackage $package) => $this->domainNormalizer->normalize($package->domain)))
            ->merge($tokens->map(fn (AccessToken $token) => $this->domainNormalizer->normalize($token->domain)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($domains === []) {
            return [
                'domains' => [],
                'attention' => [
                    'severity' => 'warning',
                    'code' => 'no_website',
                    'label' => 'No website',
                    'domain' => null,
                ],
            ];
        }

        $issues = [];

        foreach ($domains as $domain) {
            $issues = array_merge(
                $issues,
                $this->issuesForDomain(
                    $domain,
                    $this->packagesForDomain($packages, $websites, $domain),
                    $this->tokensForDomain($tokens, $websites, $domain),
                )
            );
        }

        usort($issues, fn (array $left, array $right) => $left['rank'] <=> $right['rank']);

        $worst = $issues[0] ?? null;

        if ($worst) {
            unset($worst['rank']);
        }

        return [
            'domains' => $domains,
            'attention' => $worst,
        ];
    }

    /**
     * @param  Collection<int, UserPackage>  $packages
     * @param  Collection<int, AccessToken>  $tokens
     * @return array<int, array<string, mixed>>
     */
    private function issuesForDomain(string $domain, Collection $packages, Collection $tokens): array
    {
        $issues = [];
        $activePackages = $packages->filter(fn (UserPackage $package) => (bool) $package->is_active);
        $subscription = $activePackages->sortByDesc('id')->first();
        $expiringDays = (int) config('subscription.subscription_expiring_days', 7);
        $licenseExpiringDays = (int) config('subscription.license_expiring_days', 7);

        if ($subscription && $this->isPast($subscription->expires_at)) {
            $issues[] = $this->issue(1, 'danger', 'expired', 'Expired', $domain);
        }

        $enabledTokens = $tokens->filter(fn (AccessToken $token) => (bool) $token->status);
        $expiredLicense = $enabledTokens->first(fn (AccessToken $token) => $this->isPast($token->expires_at));

        if ($expiredLicense) {
            $issues[] = $this->issue(2, 'danger', 'license_expired', 'License expired', $domain);
        }

        if ($subscription && (int) $subscription->remaining_order <= 0) {
            $issues[] = $this->issue(3, 'danger', 'quota_empty', 'Quota empty', $domain);
        }

        if ($packages->isNotEmpty() && $activePackages->isEmpty()) {
            $issues[] = $this->issue(4, 'warning', 'plan_disabled', 'Plan disabled', $domain);
        }

        if ($enabledTokens->isEmpty()) {
            $issues[] = $this->issue(5, 'warning', 'no_license', 'No license', $domain);
        }

        if ($subscription && $subscription->expires_at && ! $this->isPast($subscription->expires_at)
            && $this->isWithinDays($subscription->expires_at, $expiringDays)) {
            $issues[] = $this->issue(6, 'warning', 'expiring', 'Expiring', $domain);
        }

        $expiringLicense = $enabledTokens->first(function (AccessToken $token) use ($licenseExpiringDays) {
            return $token->expires_at
                && ! $this->isPast($token->expires_at)
                && $this->isWithinDays($token->expires_at, $licenseExpiringDays);
        });

        if ($expiringLicense) {
            $issues[] = $this->issue(7, 'warning', 'license_expiring', 'License expiring', $domain);
        }

        return $issues;
    }

    /**
     * @return array{rank: int, severity: string, code: string, label: string, domain: string}
     */
    private function issue(int $rank, string $severity, string $code, string $label, string $domain): array
    {
        return [
            'rank' => $rank,
            'severity' => $severity,
            'code' => $code,
            'label' => $label,
            'domain' => $domain,
        ];
    }

    /**
     * @param  Collection<int, UserPackage>  $packages
     * @param  Collection<int, Website>  $websites
     * @return Collection<int, UserPackage>
     */
    private function packagesForDomain(Collection $packages, Collection $websites, string $domain): Collection
    {
        $websiteIds = $websites
            ->filter(fn (Website $website) => $this->domainNormalizer->normalize($website->domain) === $domain)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $packages->filter(function (UserPackage $package) use ($domain, $websiteIds) {
            if ($package->website_id && in_array((int) $package->website_id, $websiteIds, true)) {
                return true;
            }

            return $this->domainNormalizer->normalize($package->domain) === $domain;
        });
    }

    /**
     * @param  Collection<int, AccessToken>  $tokens
     * @param  Collection<int, Website>  $websites
     * @return Collection<int, AccessToken>
     */
    private function tokensForDomain(Collection $tokens, Collection $websites, string $domain): Collection
    {
        $websiteIds = $websites
            ->filter(fn (Website $website) => $this->domainNormalizer->normalize($website->domain) === $domain)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $tokens->filter(function (AccessToken $token) use ($domain, $websiteIds) {
            if ($token->website_id && in_array((int) $token->website_id, $websiteIds, true)) {
                return true;
            }

            return $this->domainNormalizer->normalize($token->domain) === $domain;
        });
    }

    private function isPast(mixed $expiresAt): bool
    {
        if (! $expiresAt) {
            return false;
        }

        $date = $expiresAt instanceof \DateTimeInterface
            ? $expiresAt
            : \Illuminate\Support\Carbon::parse($expiresAt);

        return now()->greaterThan($date);
    }

    private function isWithinDays(mixed $expiresAt, int $days): bool
    {
        if (! $expiresAt) {
            return false;
        }

        $date = $expiresAt instanceof \DateTimeInterface
            ? $expiresAt
            : \Illuminate\Support\Carbon::parse($expiresAt);

        return now()->diffInDays($date, false) <= $days;
    }
}
