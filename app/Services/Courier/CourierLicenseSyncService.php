<?php

namespace App\Services\Courier;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\CourierLicenseLink;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourierLicenseSyncService
{
    private ?bool $schemaReady = null;

    public function __construct(
        private CourierConfigurationResolver $configurations,
    ) {
    }

    /**
     * Code can ship before the migration runs. Until the link table and
     * token column exist, booking and settings keep using the newest row.
     */
    private function schemaReady(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        return $this->schemaReady = Schema::hasTable('courier_license_links')
            && Schema::hasColumn('courier_configurations', 'access_token_id');
    }

    public function backfillExistingLinks(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('courier_license_links')) {
            return;
        }

        $userIds = CourierConfiguration::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $primary = $this->primaryTokenForUser((int) $userId);
            if (! $primary) {
                continue;
            }

            foreach (['steadfast', 'pathao', 'redx'] as $slug) {
                $config = $this->configurations->forUser((int) $userId, $slug);
                if (! $config) {
                    continue;
                }

                if (! $config->access_token_id) {
                    $config->access_token_id = $primary->id;
                    $config->save();
                }

                $tokens = $this->tokensForUser((int) $userId);
                foreach ($tokens as $token) {
                    CourierLicenseLink::query()->updateOrCreate(
                        [
                            'access_token_id' => $token->id,
                            'slug' => $slug,
                        ],
                        [
                            'user_id' => (int) $userId,
                            'courier_configuration_id' => $config->id,
                            'synced' => (int) $token->id !== (int) $primary->id,
                        ]
                    );
                }
            }
        }
    }

    public function primaryTokenForUser(int $userId): ?AccessToken
    {
        $tokens = $this->tokensForUser($userId);
        if ($tokens->isEmpty()) {
            return null;
        }

        $primaryWebsite = Website::query()
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->first();

        if ($primaryWebsite) {
            $byWebsite = $tokens->first(
                fn (AccessToken $token) => (int) $token->website_id === (int) $primaryWebsite->id
            );
            if ($byWebsite) {
                return $byWebsite;
            }

            $domain = strtolower(trim((string) $primaryWebsite->domain));
            $byDomain = $tokens->first(
                fn (AccessToken $token) => strtolower(trim((string) $token->domain)) === $domain
            );
            if ($byDomain) {
                return $byDomain;
            }
        }

        return $tokens->first();
    }

    public function bookingConfiguration(int $userId, string $slug): ?CourierConfiguration
    {
        $slug = $this->normalizeSlug($slug);
        if (! $this->schemaReady()) {
            return $this->configurations->forUser($userId, $slug);
        }

        $token = $this->currentToken($userId);

        if (! $token) {
            return $this->configurations->forUser($userId, $slug);
        }

        return $this->credentialForToken($token, $slug);
    }

    /**
     * @return array{
     *     configuration: ?CourierConfiguration,
     *     synced: bool,
     *     can_sync: bool,
     *     is_primary_site: bool,
     *     is_primary_website: bool,
     *     has_other_sites: bool,
     *     sync_source_domain: ?string
     * }
     */
    public function present(int $userId, string $slug): array
    {
        $slug = $this->normalizeSlug($slug);
        $role = $this->websiteRole($userId);
        if (! $this->schemaReady()) {
            return [
                'configuration' => $this->configurations->forUser($userId, $slug),
                'synced' => false,
                'can_sync' => false,
                'is_primary_site' => true,
                'is_primary_website' => $role['is_primary_website'],
                'has_other_sites' => $role['has_other_sites'],
                'sync_source_domain' => $role['sync_source_domain'],
            ];
        }

        $token = $this->currentToken($userId);
        $primary = $this->primaryTokenForUser($userId);
        $isPrimary = $token && $primary && (int) $token->id === (int) $primary->id;
        $source = $primary ? $this->credentialForToken($primary, $slug) : $this->configurations->forUser($userId, $slug);
        if (! $source && $this->tokensForUser($userId)->count() <= 1) {
            $source = $this->configurations->forUser($userId, $slug);
        }

        $link = $token ? $this->linkFor($token, $slug) : null;
        $synced = (bool) ($link?->synced);
        $configuration = $this->credentialForToken($token, $slug);

        return [
            'configuration' => $configuration,
            'synced' => $synced && $configuration !== null,
            'can_sync' => ! $isPrimary && $source !== null && $primary && $token && (int) $token->id !== (int) $primary->id,
            'is_primary_site' => (bool) $isPrimary || $this->tokensForUser($userId)->count() <= 1,
            'is_primary_website' => $role['is_primary_website'],
            'has_other_sites' => $role['has_other_sites'],
            'sync_source_domain' => $primary?->domain ? (string) $primary->domain : null,
        ];
    }

    public function syncCurrentToken(int $userId, string $slug): array
    {
        $slug = $this->normalizeSlug($slug);
        if (! $this->schemaReady()) {
            throw new \InvalidArgumentException('Courier sync is not available until the license link migration has run.');
        }

        $token = $this->currentToken($userId);
        $primary = $this->primaryTokenForUser($userId);

        if (! $token || ! $primary) {
            throw new \InvalidArgumentException('Courier sync needs a connected website license.');
        }

        if ((int) $token->id === (int) $primary->id) {
            throw new \InvalidArgumentException('The primary website already owns this courier configuration.');
        }

        $source = $this->credentialForToken($primary, $slug) ?: $this->configurations->forUser($userId, $slug);
        if (! $source) {
            throw new \InvalidArgumentException('The primary website has no '.$slug.' configuration to sync.');
        }

        CourierLicenseLink::query()->updateOrCreate(
            [
                'access_token_id' => $token->id,
                'slug' => $slug,
            ],
            [
                'user_id' => $userId,
                'courier_configuration_id' => $source->id,
                'synced' => true,
            ]
        );

        return $this->present($userId, $slug);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveSaveTarget(int $userId, string $slug, ?int $requestedId, array $data): array
    {
        $slug = $this->normalizeSlug($slug);
        $token = $this->currentToken($userId);
        $presented = $this->present($userId, $slug);

        if ($presented['synced'] && $this->credentialsMatch($presented['configuration'], $data)) {
            return ['action' => 'noop', 'configuration' => $presented['configuration']];
        }

        if (! $token || $presented['is_primary_site']) {
            $configuration = null;
            if ($requestedId) {
                $configuration = CourierConfiguration::query()
                    ->where('id', $requestedId)
                    ->where('user_id', $userId)
                    ->first();
                if (! $configuration) {
                    return ['action' => 'reject', 'configuration' => null];
                }
                if ($token && $configuration->access_token_id && (int) $configuration->access_token_id !== (int) $token->id) {
                    return ['action' => 'reject', 'configuration' => null];
                }
            }
            if (! $configuration && $token) {
                $configuration = $this->legacyConfigurationForToken($token, $slug);
            }
            if (! $configuration) {
                $configuration = $this->configurations->forUser($userId, $slug);
            }

            return [
                'action' => $configuration ? 'update' : 'create',
                'configuration' => $configuration,
                'own_token' => $token,
            ];
        }

        if ($requestedId) {
            $requested = CourierConfiguration::query()
                ->where('id', $requestedId)
                ->where('user_id', $userId)
                ->first();
            $sourceId = (int) ($presented['configuration']->id ?? 0);
            if ($requested && $presented['synced'] && (int) $requested->id === $sourceId) {
                $requested = null;
            }
            if ($requested && (int) $requested->access_token_id === (int) $token->id) {
                return ['action' => 'update', 'configuration' => $requested, 'own_token' => $token];
            }
        }

        $owned = CourierConfiguration::query()
            ->where('user_id', $userId)
            ->where('access_token_id', $token->id)
            ->where('slug', $slug)
            ->orderByDesc('id')
            ->first();

        return [
            'action' => $owned ? 'update' : 'create',
            'configuration' => $owned,
            'own_token' => $token,
        ];
    }

    public function markOwned(int $userId, AccessToken $token, CourierConfiguration $configuration, string $slug): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        $slug = $this->normalizeSlug($slug);
        $primary = $this->primaryTokenForUser($userId);
        $ownsPrimaryRow = $primary && (int) $token->id === (int) $primary->id;

        if ($ownsPrimaryRow && ! $configuration->access_token_id) {
            $configuration->access_token_id = $token->id;
            $configuration->save();
        }

        if (! $ownsPrimaryRow && (int) $configuration->access_token_id !== (int) $token->id) {
            $configuration->access_token_id = $token->id;
            $configuration->save();
        }

        CourierLicenseLink::query()->updateOrCreate(
            [
                'access_token_id' => $token->id,
                'slug' => $slug,
            ],
            [
                'user_id' => $userId,
                'courier_configuration_id' => $configuration->id,
                'synced' => false,
            ]
        );
    }

    public function credentialForToken(?AccessToken $token, string $slug): ?CourierConfiguration
    {
        if (! $token) {
            return null;
        }

        $slug = $this->normalizeSlug($slug);
        if (! $this->schemaReady()) {
            return $this->configurations->forUser((int) $token->tokenable_id, $slug);
        }

        $link = $this->linkFor($token, $slug);
        if ($link) {
            $config = CourierConfiguration::query()
                ->where('id', $link->courier_configuration_id)
                ->where('user_id', $token->tokenable_id)
                ->first();
            if ($config) {
                return $config;
            }
        }

        if ($this->tokensForUser((int) $token->tokenable_id)->count() <= 1) {
            return $this->legacyConfigurationForToken($token, $slug);
        }

        $primary = $this->primaryTokenForUser((int) $token->tokenable_id);
        if ($primary && (int) $primary->id === (int) $token->id) {
            return $this->legacyConfigurationForToken($token, $slug);
        }

        return null;
    }

    public function legacyConfigurationForToken(AccessToken $token, string $slug): ?CourierConfiguration
    {
        $slug = $this->normalizeSlug($slug);
        if (! $this->schemaReady()) {
            return $this->configurations->forUser((int) $token->tokenable_id, $slug);
        }

        return CourierConfiguration::query()
            ->where('user_id', $token->tokenable_id)
            ->where('slug', $slug)
            ->where(function ($query) use ($token) {
                $query->whereNull('access_token_id')
                    ->orWhere('access_token_id', $token->id);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function credentialsMatch(?CourierConfiguration $configuration, array $data): bool
    {
        if (! $configuration) {
            return false;
        }

        $nextKey = trim((string) ($data['api_key'] ?? ''));
        $nextSecret = trim((string) ($data['secret_key'] ?? ''));
        $currentKey = (string) $configuration->api_key;
        $currentSecret = (string) $configuration->secret_key;
        $sameKey = $nextKey !== '' && $nextKey === $currentKey;
        $sameSecret = $nextSecret === '' || $nextSecret === $currentSecret;

        return $sameKey && $sameSecret;
    }

    private function linkFor(AccessToken $token, string $slug): ?CourierLicenseLink
    {
        if (! $this->schemaReady()) {
            return null;
        }

        return CourierLicenseLink::query()
            ->where('access_token_id', $token->id)
            ->where('slug', $this->normalizeSlug($slug))
            ->first();
    }

    private function currentToken(int $userId): ?AccessToken
    {
        $request = request();
        if (! $request) {
            return null;
        }

        $token = app(CourierAccountService::class)->resolveAccessToken($request);
        if (! $token || (int) $token->tokenable_id !== $userId) {
            return null;
        }

        return $token;
    }

    private function tokensForUser(int $userId)
    {
        return AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $userId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Which website this license belongs to. A store with one license is primary.
     *
     * @return array{is_primary: bool, has_other_sites: bool, primary_domain: ?string}
     */
    public function roleForLicense(AccessToken $token): array
    {
        $userId = (int) $token->tokenable_id;
        $tokens = $this->tokensForUser($userId);
        $primary = $this->primaryTokenForUser($userId);
        $hasOtherSites = $tokens->count() > 1;
        $isPrimary = ! $hasOtherSites
            || ($primary && (int) $token->id === (int) $primary->id);

        return [
            'is_primary' => $isPrimary,
            'has_other_sites' => $hasOtherSites,
            'primary_domain' => $primary && $primary->domain ? (string) $primary->domain : null,
        ];
    }

    /**
     * Display role for the plugin. Independent of the pre-migration save path,
     * which keeps treating every site as primary until the link table exists.
     *
     * @return array{is_primary_website: bool, has_other_sites: bool, sync_source_domain: ?string}
     */
    private function websiteRole(int $userId): array
    {
        $token = $this->currentToken($userId);
        if (! $token) {
            return [
                'is_primary_website' => true,
                'has_other_sites' => false,
                'sync_source_domain' => null,
            ];
        }

        $role = $this->roleForLicense($token);

        return [
            'is_primary_website' => $role['is_primary'],
            'has_other_sites' => $role['has_other_sites'],
            'sync_source_domain' => $role['primary_domain'],
        ];
    }

    private function normalizeSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }
}
