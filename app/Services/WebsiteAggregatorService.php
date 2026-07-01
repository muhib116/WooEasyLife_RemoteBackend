<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Collection;

class WebsiteAggregatorService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user): array
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->orderBy('id')
            ->get();

        $websites = Website::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        $employees = MerchantEmployee::query()
            ->with(['websites:id', 'role:id,name'])
            ->where('merchant_user_id', $user->id)
            ->where('status', true)
            ->orderBy('name')
            ->get();

        $results = collect();

        foreach ($websites as $website) {
            $results->push($this->buildWebsiteFromRecord($website, $packages, $tokens, $employees));
        }

        $coveredDomains = $websites->pluck('domain');

        $businesses = UserBusiness::query()
            ->where('user_id', $user->id)
            ->get();

        $orphanDomains = $this->collectDomains($packages, $tokens, $businesses)
            ->reject(fn (string $domain) => $coveredDomains->contains($domain));

        foreach ($orphanDomains as $domain) {
            $results->push($this->buildWebsite($domain, $packages, $tokens, $employees));
        }

        return $results->values()->all();
    }

    private function collectDomains(Collection $packages, Collection $tokens, Collection $businesses): Collection
    {
        $domains = collect();

        foreach ($packages as $package) {
            $normalized = $this->domainNormalizer->normalize($package->domain);
            if ($normalized) {
                $domains->push($normalized);
            }
        }

        foreach ($tokens as $token) {
            $normalized = $this->domainNormalizer->normalize($token->domain);
            if ($normalized) {
                $domains->push($normalized);
            }
        }

        foreach ($businesses as $business) {
            $normalized = $this->domainNormalizer->normalize($business->domain);
            if ($normalized) {
                $domains->push($normalized);
            }
        }

        return $domains->unique()->sort()->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWebsiteFromRecord(
        Website $website,
        Collection $packages,
        Collection $tokens,
        Collection $employees
    ): array {
        $domainPackages = $this->packagesForWebsite($website, $packages);
        $domainTokens = $this->tokensForWebsite($website, $tokens);
        $linkedEmployees = $this->employeesForWebsite($employees, $website->id);

        return $this->composeWebsitePayload(
            $website->domain,
            $domainPackages,
            $domainTokens,
            $website->id,
            $website->title,
            (bool) $website->status,
            (bool) $website->is_primary,
            $linkedEmployees,
            $website->base_url
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWebsite(
        string $domain,
        Collection $packages,
        Collection $tokens,
        Collection $employees
    ): array {
        $domainPackages = $packages->filter(
            fn (UserPackage $package) => $this->domainNormalizer->normalize($package->domain) === $domain
        );

        $domainTokens = $tokens->filter(
            fn (AccessToken $token) => $this->domainNormalizer->normalize($token->domain) === $domain
        );

        return $this->composeWebsitePayload($domain, $domainPackages, $domainTokens);
    }

    private function packagesForWebsite(Website $website, Collection $packages): Collection
    {
        return $packages->filter(function (UserPackage $package) use ($website) {
            if ($package->website_id) {
                return (int) $package->website_id === (int) $website->id;
            }

            return $this->domainNormalizer->normalize($package->domain) === $website->domain;
        });
    }

    private function tokensForWebsite(Website $website, Collection $tokens): Collection
    {
        return $tokens->filter(function (AccessToken $token) use ($website) {
            if ($token->website_id) {
                return (int) $token->website_id === (int) $website->id;
            }

            return $this->domainNormalizer->normalize($token->domain) === $website->domain;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function composeWebsitePayload(
        string $domain,
        Collection $domainPackages,
        Collection $domainTokens,
        ?int $websiteId = null,
        ?string $title = null,
        ?bool $websiteStatus = null,
        ?bool $isPrimary = null,
        array $linkedEmployees = [],
        ?string $baseUrl = null
    ): array {
        $subscription = $this->primarySubscription($domainPackages);
        $licenses = $domainTokens->map(fn (AccessToken $token) => [
            'id' => $token->id,
            'title' => $token->title ?? $token->name,
            'status' => (bool) $token->status,
            'expires_at' => $token->expires_at,
            'last_used_at' => $token->last_used_at,
            'last_used_ago' => optional($token->last_used_at)?->diffForHumans(),
            'has_token' => ! empty($token->access_key),
        ])->values()->all();

        $health = $this->healthForWebsite($domain, $subscription, $licenses);

        return [
            'id' => $websiteId,
            'domain' => $domain,
            'title' => $title,
            'base_url' => $baseUrl,
            'website_status' => $websiteStatus,
            'is_primary' => $isPrimary,
            'display_url' => $baseUrl ? rtrim($baseUrl, '/') : 'https://' . $domain,
            'subscription' => $subscription,
            'licenses' => $licenses,
            'health' => $health,
            'employees' => $linkedEmployees,
            'employee_count' => count($linkedEmployees),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeesForWebsite(Collection $employees, ?int $websiteId): array
    {
        if (! $websiteId) {
            return [];
        }

        return $employees
            ->filter(fn (MerchantEmployee $employee) => $this->employeeLinkedToWebsite($employee, $websiteId))
            ->map(fn (MerchantEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'photo_url' => $employee->photo_url,
                'role' => $employee->role?->name,
            ])
            ->values()
            ->all();
    }

    private function employeeLinkedToWebsite(MerchantEmployee $employee, int $websiteId): bool
    {
        return $employee->isAssignedToWebsite($websiteId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function primarySubscription(Collection $domainPackages): ?array
    {
        if ($domainPackages->isEmpty()) {
            return null;
        }

        $activeWithQuota = $domainPackages
            ->where('is_active', true)
            ->where('remaining_order', '>', 0)
            ->sortBy('id')
            ->first();

        $active = $activeWithQuota
            ?? $domainPackages->where('is_active', true)->sortByDesc('id')->first()
            ?? $domainPackages->sortByDesc('id')->first();

        if (! $active) {
            return null;
        }

        return [
            'id' => $active->id,
            'package_hub_id' => $active->package_hub_id,
            'title' => $active->title,
            'plan_type' => $active->plan_type ?? 'legacy',
            'is_active' => (bool) $active->is_active,
            'remaining_order' => (int) $active->remaining_order,
            'total_order_can_handle' => (int) $active->total_order_can_handle,
            'total_order_handled' => (int) $active->total_order_handled,
            'total_cost' => (float) $active->total_cost,
            'per_order_rate' => (float) $active->per_order_rate,
            'order_rate_token' => $active->order_rate_token !== null
                ? (int) $active->order_rate_token
                : null,
            'package_duration' => $active->package_duration,
            'expires_at' => $active->expires_at,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $licenses
     * @return array<string, mixed>
     */
    private function healthForWebsite(string $domain, ?array $subscription, array $licenses): array
    {
        $issues = [];
        $subscriptionExpired = $this->subscriptionIsExpired($subscription);

        if (! $subscription) {
            $issues[] = 'No subscription plan assigned for this domain.';
        } elseif (! $subscription['is_active']) {
            $issues[] = 'Subscription plan is disabled.';
        } elseif ($subscription['remaining_order'] <= 0) {
            $issues[] = ($subscription['plan_type'] ?? 'legacy') === 'catalog'
                ? 'Order tokens exhausted.'
                : 'Order quota exhausted.';
        } elseif ($subscriptionExpired) {
            $issues[] = 'Subscription has expired.';
        }

        if ($licenses === []) {
            $issues[] = 'No license key generated for this domain.';
        } else {
            $enabledLicense = collect($licenses)->contains(fn ($license) => $license['status']);

            if (! $enabledLicense) {
                $issues[] = 'All license keys are disabled.';
            }

            $used = collect($licenses)->contains(fn ($license) => $license['last_used_at'] !== null);

            if ($enabledLicense && ! $used) {
                $issues[] = 'Plugin has not connected yet (no API usage recorded).';
            }

            $expiredLicense = collect($licenses)->contains(function ($license) {
                if (! $license['status'] || empty($license['expires_at'])) {
                    return false;
                }

                $expiresAt = $license['expires_at'];

                return now()->greaterThan(
                    $expiresAt instanceof \Illuminate\Support\Carbon
                        ? $expiresAt
                        : \Illuminate\Support\Carbon::parse($expiresAt)
                );
            });

            if ($expiredLicense) {
                $issues[] = 'An enabled license key has expired.';
            }
        }

        $hasEnabledLicense = collect($licenses)->contains(fn ($license) => $license['status']);
        $pluginConnected = collect($licenses)->contains(
            fn ($license) => $license['status'] && $license['last_used_at'] !== null
        );

        $configured = $subscription
            && $subscription['is_active']
            && $subscription['remaining_order'] > 0
            && $hasEnabledLicense
            && ! $subscriptionExpired;

        $ready = $configured && $pluginConnected;

        $status = 'incomplete';

        if ($ready) {
            $status = 'connected';
        } elseif ($configured) {
            $status = 'configured';
        } elseif ($subscription && (
            ! $subscription['is_active']
            || $subscription['remaining_order'] <= 0
            || $subscriptionExpired
        )) {
            $status = 'disabled';
        }

        return [
            'status' => $status,
            'ready_for_plugin' => $ready,
            'configured_for_plugin' => $configured,
            'plugin_connected' => $pluginConnected,
            'subscription_expired' => $subscriptionExpired,
            'issues' => $issues,
        ];
    }

    private function subscriptionIsExpired(?array $subscription): bool
    {
        if (! $subscription || empty($subscription['expires_at'])) {
            return false;
        }

        $expiresAt = $subscription['expires_at'];

        return now()->greaterThan(
            $expiresAt instanceof \Illuminate\Support\Carbon
                ? $expiresAt
                : \Illuminate\Support\Carbon::parse($expiresAt)
        );
    }
}
