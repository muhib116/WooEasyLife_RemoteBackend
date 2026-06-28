<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WebsiteSyncService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * Find or create a website row for a merchant domain.
     * Does not modify existing package/token domain strings.
     */
    public function resolveForUser(User $user, string $domain, ?string $title = null): ?Website
    {
        $normalized = $this->domainNormalizer->normalize($domain);
        if (! $normalized) {
            return null;
        }

        $website = Website::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'domain' => $normalized,
            ],
            [
                'title' => $title ?: $normalized,
                'status' => true,
                'is_primary' => ! Website::query()->where('user_id', $user->id)->exists(),
            ]
        );

        return $website;
    }

    /**
     * Link a subscription row to its website without changing domain.
     */
    public function linkUserPackage(UserPackage $userPackage): void
    {
        if (! $userPackage->user_id || ! $userPackage->domain) {
            return;
        }

        $user = User::query()->find($userPackage->user_id);
        if (! $user) {
            return;
        }

        $website = $this->resolveForUser($user, $userPackage->domain, $userPackage->title);
        if (! $website || $userPackage->website_id === $website->id) {
            return;
        }

        UserPackage::query()
            ->whereKey($userPackage->id)
            ->update(['website_id' => $website->id]);
    }

    /**
     * Link a license token to its website without changing domain.
     */
    public function linkAccessToken(AccessToken $accessToken): void
    {
        if ($accessToken->tokenable_type !== User::class || ! $accessToken->tokenable_id || ! $accessToken->domain) {
            return;
        }

        $user = User::query()->find($accessToken->tokenable_id);
        if (! $user) {
            return;
        }

        $website = $this->resolveForUser($user, $accessToken->domain, $accessToken->title ?? $accessToken->name);
        if (! $website || $accessToken->website_id === $website->id) {
            return;
        }

        AccessToken::query()
            ->whereKey($accessToken->id)
            ->update(['website_id' => $website->id]);
    }

    /**
     * Backfill websites and website_id links for one merchant.
     *
     * @return array{websites_created: int, websites_existing: int, packages_linked: int, tokens_linked: int}
     */
    public function backfillUser(User $user, bool $dryRun = false): array
    {
        $stats = [
            'websites_created' => 0,
            'websites_existing' => 0,
            'packages_linked' => 0,
            'tokens_linked' => 0,
        ];

        $domains = $this->collectNormalizedDomainsForUser($user);
        if ($domains->isEmpty()) {
            return $stats;
        }

        $run = function () use ($user, $domains, $dryRun, &$stats) {
            foreach ($domains as $normalizedDomain) {
                $website = Website::query()
                    ->where('user_id', $user->id)
                    ->where('domain', $normalizedDomain)
                    ->first();

                if ($website) {
                    $stats['websites_existing']++;
                } else {
                    $stats['websites_created']++;

                    if (! $dryRun) {
                        $website = Website::query()->create([
                            'user_id' => $user->id,
                            'domain' => $normalizedDomain,
                            'title' => $normalizedDomain,
                            'status' => true,
                            'is_primary' => ! Website::query()->where('user_id', $user->id)->exists(),
                        ]);
                    }
                }

                $stats['packages_linked'] += $this->countOrLinkPackagesForDomain(
                    $user,
                    $website,
                    $normalizedDomain,
                    $dryRun
                );
                $stats['tokens_linked'] += $this->countOrLinkTokensForDomain(
                    $user,
                    $website,
                    $normalizedDomain,
                    $dryRun
                );
            }

            if (! $dryRun) {
                $this->ensurePrimaryWebsite($user);
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $stats;
    }

    private function countOrLinkPackagesForDomain(User $user, ?Website $website, string $normalizedDomain, bool $dryRun): int
    {
        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->whereNull('website_id')
            ->get()
            ->filter(fn (UserPackage $package) => $this->domainNormalizer->normalize($package->domain) === $normalizedDomain);

        if ($dryRun || ! $website) {
            return $packages->count();
        }

        $count = 0;
        foreach ($packages as $package) {
            UserPackage::query()
                ->whereKey($package->id)
                ->update(['website_id' => $website->id]);
            $count++;
        }

        return $count;
    }

    private function countOrLinkTokensForDomain(User $user, ?Website $website, string $normalizedDomain, bool $dryRun): int
    {
        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->whereNull('website_id')
            ->get()
            ->filter(fn (AccessToken $token) => $this->domainNormalizer->normalize($token->domain) === $normalizedDomain);

        if ($dryRun || ! $website) {
            return $tokens->count();
        }

        $count = 0;
        foreach ($tokens as $token) {
            AccessToken::query()
                ->whereKey($token->id)
                ->update(['website_id' => $website->id]);
            $count++;
        }

        return $count;
    }

    private function collectNormalizedDomainsForUser(User $user): Collection
    {
        $domains = collect();

        UserPackage::query()
            ->where('user_id', $user->id)
            ->pluck('domain')
            ->each(function (?string $domain) use ($domains) {
                $normalized = $this->domainNormalizer->normalize($domain);
                if ($normalized) {
                    $domains->push($normalized);
                }
            });

        AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->pluck('domain')
            ->each(function (?string $domain) use ($domains) {
                $normalized = $this->domainNormalizer->normalize($domain);
                if ($normalized) {
                    $domains->push($normalized);
                }
            });

        UserBusiness::query()
            ->where('user_id', $user->id)
            ->pluck('domain')
            ->each(function (?string $domain) use ($domains) {
                $normalized = $this->domainNormalizer->normalize($domain);
                if ($normalized) {
                    $domains->push($normalized);
                }
            });

        return $domains->unique()->sort()->values();
    }

    private function ensurePrimaryWebsite(User $user): void
    {
        $websites = Website::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();

        if ($websites->isEmpty()) {
            return;
        }

        if ($websites->contains(fn (Website $website) => $website->is_primary)) {
            return;
        }

        Website::query()
            ->whereKey($websites->first()->id)
            ->update(['is_primary' => true]);
    }
}
