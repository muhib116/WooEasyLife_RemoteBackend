<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use Illuminate\Validation\ValidationException;

class DomainAvailabilityService
{
    public function __construct(
        protected DomainNormalizer $domainNormalizer
    ) {
    }

    public function isEnforcementEnabled(): bool
    {
        return (bool) config('domains.enforce_global_uniqueness', true);
    }

    public function normalize(?string $domain): ?string
    {
        return $this->domainNormalizer->normalize($domain);
    }

    public function findOwnerUserId(string $normalizedDomain): ?int
    {
        $normalizedDomain = $this->normalize($normalizedDomain);
        if (! $normalizedDomain) {
            return null;
        }

        $ownerId = $this->findOwnerFromTable(Website::class, 'domain', 'user_id', $normalizedDomain);
        if ($ownerId !== null) {
            return $ownerId;
        }

        $ownerId = $this->findOwnerFromTable(UserPackage::class, 'domain', 'user_id', $normalizedDomain);
        if ($ownerId !== null) {
            return $ownerId;
        }

        $ownerId = $this->findOwnerFromAccessTokens($normalizedDomain);
        if ($ownerId !== null) {
            return $ownerId;
        }

        $ownerId = $this->findOwnerFromTable(UserBusiness::class, 'domain', 'user_id', $normalizedDomain);
        if ($ownerId !== null) {
            return $ownerId;
        }

        return $this->findOwnerFromTable(SmsBalance::class, 'domain', 'user_id', $normalizedDomain);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function findOwnerFromTable(
        string $modelClass,
        string $domainColumn,
        string $ownerColumn,
        string $normalizedDomain
    ): ?int {
        $query = $modelClass::query();
        $this->domainNormalizer->constrainMatchingDomain($query, $domainColumn, $normalizedDomain);

        foreach ($query->limit(25)->get([$domainColumn, $ownerColumn]) as $record) {
            if (! $this->domainNormalizer->matches($record->{$domainColumn}, $normalizedDomain)) {
                continue;
            }

            return (int) $record->{$ownerColumn};
        }

        return null;
    }

    private function findOwnerFromAccessTokens(string $normalizedDomain): ?int
    {
        $query = AccessToken::query()->where('tokenable_type', User::class);
        $this->domainNormalizer->constrainMatchingDomain($query, 'domain', $normalizedDomain);

        foreach ($query->limit(25)->get(['domain', 'tokenable_id']) as $token) {
            if (! $this->domainNormalizer->matches($token->domain, $normalizedDomain)) {
                continue;
            }

            return (int) $token->tokenable_id;
        }

        return null;
    }

    /**
     * Reject when this merchant already has a website for the domain (add-website flow).
     */
    public function rejectDuplicateWebsiteForUser(User $user, string $domain): void
    {
        $normalized = $this->normalize($domain);
        if (! $normalized) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $exists = Website::query()
            ->where('user_id', $user->id)
            ->where('domain', $normalized)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'domain' => 'This merchant already has a website with this domain.',
            ]);
        }
    }

    /**
     * Reject when a websites row exists for another merchant (always enforced).
     */
    public function rejectCrossUserWebsiteClaim(User $user, string $domain, bool $forAdmin = false): void
    {
        $normalized = $this->normalize($domain);
        if (! $normalized) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $website = Website::query()
            ->where('domain', $normalized)
            ->first();

        if (! $website || (int) $website->user_id === (int) $user->id) {
            return;
        }

        $message = 'This store domain is already registered on WooEasyLife. Contact support if you need access.';

        if ($forAdmin) {
            $message .= " (Registered to merchant #{$website->user_id}.)";
        }

        throw ValidationException::withMessages([
            'domain' => $message,
        ]);
    }

    public function isAvailableForUser(User $user, string $domain): bool
    {
        if (! $this->isEnforcementEnabled()) {
            return true;
        }

        $normalized = $this->normalize($domain);
        if (! $normalized) {
            return false;
        }

        $ownerId = $this->findOwnerUserId($normalized);

        return $ownerId === null || $ownerId === (int) $user->id;
    }

    public function assertAvailableForUser(User $user, string $domain, bool $forAdmin = false): void
    {
        if (! $this->isEnforcementEnabled()) {
            return;
        }

        $normalized = $this->normalize($domain);
        if (! $normalized) {
            throw ValidationException::withMessages([
                'domain' => 'Invalid domain',
            ]);
        }

        $ownerId = $this->findOwnerUserId($normalized);

        if ($ownerId === null || $ownerId === (int) $user->id) {
            return;
        }

        $message = 'This store domain is already registered on WooEasyLife. Contact support if you need access.';

        if ($forAdmin) {
            $message .= " (Registered to merchant #{$ownerId}.)";
        }

        throw ValidationException::withMessages([
            'domain' => $message,
        ]);
    }

    /**
     * @return array<int, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>}
     */
    public function findCrossUserConflicts(): array
    {
        $conflicts = [];

        $this->collectWebsiteConflicts($conflicts);
        $this->collectPackageConflicts($conflicts);
        $this->collectTokenConflicts($conflicts);
        $this->collectBusinessConflicts($conflicts);
        $this->collectSmsBalanceConflicts($conflicts);

        return collect($conflicts)
            ->filter(fn (array $conflict) => count($conflict['user_ids']) > 1)
            ->sortBy('domain')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function collectWebsiteConflicts(array &$conflicts): void
    {
        Website::query()
            ->orderBy('domain')
            ->get(['id', 'user_id', 'domain'])
            ->each(function (Website $website) use (&$conflicts) {
                $normalized = $this->normalize($website->domain);
                if (! $normalized) {
                    return;
                }

                $this->recordConflict($conflicts, $normalized, (int) $website->user_id, 'websites', (int) $website->id);
            });
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function collectPackageConflicts(array &$conflicts): void
    {
        UserPackage::query()
            ->whereNotNull('domain')
            ->orderBy('id')
            ->get(['id', 'user_id', 'domain'])
            ->each(function (UserPackage $package) use (&$conflicts) {
                $normalized = $this->normalize($package->domain);
                if (! $normalized) {
                    return;
                }

                $this->recordConflict($conflicts, $normalized, (int) $package->user_id, 'user_packages', (int) $package->id);
            });
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function collectTokenConflicts(array &$conflicts): void
    {
        AccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereNotNull('domain')
            ->orderBy('id')
            ->get(['id', 'tokenable_id', 'domain'])
            ->each(function (AccessToken $token) use (&$conflicts) {
                $normalized = $this->normalize($token->domain);
                if (! $normalized) {
                    return;
                }

                $this->recordConflict(
                    $conflicts,
                    $normalized,
                    (int) $token->tokenable_id,
                    'personal_access_tokens',
                    (int) $token->id
                );
            });
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function collectBusinessConflicts(array &$conflicts): void
    {
        UserBusiness::query()
            ->whereNotNull('domain')
            ->orderBy('id')
            ->get(['id', 'user_id', 'domain'])
            ->each(function (UserBusiness $business) use (&$conflicts) {
                $normalized = $this->normalize($business->domain);
                if (! $normalized) {
                    return;
                }

                $this->recordConflict(
                    $conflicts,
                    $normalized,
                    (int) $business->user_id,
                    'user_businesses',
                    (int) $business->id
                );
            });
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function collectSmsBalanceConflicts(array &$conflicts): void
    {
        SmsBalance::query()
            ->whereNotNull('domain')
            ->orderBy('id')
            ->get(['id', 'user_id', 'domain'])
            ->each(function (SmsBalance $balance) use (&$conflicts) {
                $normalized = $this->normalize($balance->domain);
                if (! $normalized) {
                    return;
                }

                $this->recordConflict(
                    $conflicts,
                    $normalized,
                    (int) $balance->user_id,
                    'sms_balances',
                    (int) $balance->id
                );
            });
    }

    /**
     * @param  array<string, array{domain: string, user_ids: array<int, int>, sources: array<string, array<int, int>}>  $conflicts
     */
    private function recordConflict(
        array &$conflicts,
        string $normalizedDomain,
        int $userId,
        string $source,
        int $recordId
    ): void {
        if (! isset($conflicts[$normalizedDomain])) {
            $conflicts[$normalizedDomain] = [
                'domain' => $normalizedDomain,
                'user_ids' => [],
                'sources' => [],
            ];
        }

        $conflicts[$normalizedDomain]['user_ids'][$userId] = $userId;
        $conflicts[$normalizedDomain]['sources'][$source][] = $recordId;
    }
}
