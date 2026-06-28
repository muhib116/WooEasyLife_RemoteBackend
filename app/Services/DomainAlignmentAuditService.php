<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Collection;

class DomainAlignmentAuditService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function auditUser(User $user): array
    {
        $issues = [];

        $packages = UserPackage::query()
            ->where('user_id', $user->id)
            ->get();

        $tokens = AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->get();

        foreach ($tokens as $token) {
            $issues = array_merge(
                $issues,
                $this->auditToken($user, $token, $packages)
            );
        }

        $issues = array_merge($issues, $this->auditDomainStringVariants($user, $packages, $tokens));

        return $issues;
    }

    /**
     * @return array<string, int>
     */
    public function summarize(Collection $issues): array
    {
        return $issues
            ->groupBy('type')
            ->map(fn (Collection $group) => $group->count())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditToken(User $user, AccessToken $token, Collection $packages): array
    {
        $issues = [];
        $tokenDomain = $token->domain;
        $normalizedTokenDomain = $this->domainNormalizer->normalize($tokenDomain);

        if (! $normalizedTokenDomain) {
            $issues[] = $this->issue(
                $user,
                'invalid_token_domain',
                'medium',
                'License domain is missing or invalid.',
                $token,
                null,
                $tokenDomain,
                null
            );

            return $issues;
        }

        $exactPackageMatch = $packages->first(
            fn (UserPackage $package) => $package->domain === $tokenDomain && $package->is_active
        );

        $normalizedPackageMatches = $packages->filter(
            fn (UserPackage $package) => $this->domainNormalizer->normalize($package->domain) === $normalizedTokenDomain
        );

        $activeNormalizedMatches = $normalizedPackageMatches->where('is_active', true);

        if (! $exactPackageMatch && $activeNormalizedMatches->isNotEmpty()) {
            $samplePackage = $activeNormalizedMatches->sortByDesc('id')->first();

            $issues[] = $this->issue(
                $user,
                'domain_string_inconsistency',
                'medium',
                'License and plan domains normalize to the same hostname but use different raw strings. Run php artisan domains:normalize for consistency.',
                $token,
                $samplePackage,
                $tokenDomain,
                $samplePackage?->domain,
                $normalizedTokenDomain
            );
        }

        if ($activeNormalizedMatches->isEmpty() && $normalizedPackageMatches->isNotEmpty()) {
            $samplePackage = $normalizedPackageMatches->sortByDesc('id')->first();

            $issues[] = $this->issue(
                $user,
                'inactive_plan_only',
                'medium',
                'Matching plan exists but is inactive while the license domain aligns by hostname only.',
                $token,
                $samplePackage,
                $tokenDomain,
                $samplePackage?->domain,
                $normalizedTokenDomain
            );
        }

        if ($normalizedPackageMatches->isEmpty()) {
            $issues[] = $this->issue(
                $user,
                'token_without_plan',
                'high',
                'No subscription plan exists for this license domain.',
                $token,
                null,
                $tokenDomain,
                null,
                $normalizedTokenDomain
            );
        }

        return $issues;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function auditDomainStringVariants(User $user, Collection $packages, Collection $tokens): array
    {
        $issues = [];
        $grouped = collect();

        foreach ($packages as $package) {
            $normalized = $this->domainNormalizer->normalize($package->domain);
            if (! $normalized) {
                continue;
            }

            $grouped->push([
                'source' => 'package',
                'id' => $package->id,
                'raw_domain' => $package->domain,
                'normalized' => $normalized,
            ]);
        }

        foreach ($tokens as $token) {
            $normalized = $this->domainNormalizer->normalize($token->domain);
            if (! $normalized) {
                continue;
            }

            $grouped->push([
                'source' => 'token',
                'id' => $token->id,
                'raw_domain' => $token->domain,
                'normalized' => $normalized,
            ]);
        }

        foreach ($grouped->groupBy('normalized') as $normalized => $entries) {
            $rawValues = $entries->pluck('raw_domain')->unique()->values();

            if ($rawValues->count() <= 1) {
                continue;
            }

            $issues[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'type' => 'domain_string_variants',
                'severity' => 'medium',
                'message' => 'Multiple raw domain strings map to the same hostname.',
                'normalized_domain' => $normalized,
                'raw_domains' => $rawValues->all(),
                'entries' => $entries->values()->all(),
            ];
        }

        return $issues;
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(
        User $user,
        string $type,
        string $severity,
        string $message,
        AccessToken $token,
        ?UserPackage $package,
        ?string $tokenDomain,
        ?string $packageDomain,
        ?string $normalizedDomain = null
    ): array {
        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'token_id' => $token->id,
            'token_domain' => $tokenDomain,
            'package_id' => $package?->id,
            'package_domain' => $packageDomain,
            'normalized_domain' => $normalizedDomain ?? $this->domainNormalizer->normalize($tokenDomain),
        ];
    }
}
