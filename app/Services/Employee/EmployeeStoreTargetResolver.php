<?php

namespace App\Services\Employee;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\User;
use App\Models\Website;
use App\Services\DomainNormalizer;
use App\Services\WebsiteUrlResolver;
use Illuminate\Support\Collection;

class EmployeeStoreTargetResolver
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer,
        protected WebsiteUrlResolver $websiteUrlResolver
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function resolveEffectiveWebsiteIds(User $merchant, MerchantEmployee $employee): array
    {
        $employee->loadMissing('websites');

        $websiteIds = $employee->websites
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($websiteIds !== []) {
            return $websiteIds;
        }

        if ($employee->website_id) {
            return [(int) $employee->website_id];
        }

        return Website::query()
            ->where('user_id', $merchant->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $websiteIds
     * @return Collection<int, array{website_id: int, domain: string, site_urls: array<int, string>, access_token: AccessToken}>
     */
    public function resolveTargets(User $merchant, array $websiteIds): Collection
    {
        if ($websiteIds === []) {
            return collect();
        }

        $websites = Website::query()
            ->where('user_id', $merchant->id)
            ->whereIn('id', $websiteIds)
            ->orderBy('id')
            ->get();

        if ($websites->isEmpty()) {
            return collect();
        }

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $merchant->id)
            ->where('status', true)
            ->whereNotNull('access_key')
            ->orderBy('id')
            ->get();

        return $websites
            ->map(function (Website $website) use ($tokens) {
                $token = $this->tokenForWebsite($website, $tokens);

                if (! $token) {
                    return null;
                }

                return [
                    'website_id' => (int) $website->id,
                    'domain' => (string) $website->domain,
                    'site_urls' => $this->websiteUrlResolver->siteUrlCandidates($website, $token),
                    'access_token' => $token,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, AccessToken>  $tokens
     */
    private function tokenForWebsite(Website $website, Collection $tokens): ?AccessToken
    {
        $matches = $tokens->filter(function (AccessToken $token) use ($website) {
            if ($token->website_id) {
                return (int) $token->website_id === (int) $website->id;
            }

            return $this->domainNormalizer->matches($token->domain, $website->domain);
        });

        return $matches->first();
    }
}
