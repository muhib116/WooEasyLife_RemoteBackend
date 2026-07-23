<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google Analytics Data API (GA4) client.
 *
 * Prefers OAuth refresh-token flow using GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
 * (or SEO_GA_* overrides). Refresh token may come from .env or one-click admin connect
 * (PlatformSetting). Falls back to a static SEO_GA_ACCESS_TOKEN when set.
 */
class GoogleAnalyticsClient
{
    public function __construct(
        private GaCredentialStore $credentials,
    ) {}

    public function configured(): bool
    {
        return filled($this->propertyId()) && filled($this->resolveAccessToken());
    }

    /**
     * @return array{
     *     property_id: string|null,
     *     has_property_id: bool,
     *     has_client_id: bool,
     *     has_client_secret: bool,
     *     has_refresh_token: bool,
     *     has_static_access_token: bool,
     *     auth_mode: string,
     *     ready: bool,
     *     can_connect: bool,
     *     connect_url: string|null,
     *     disconnect_url: string|null,
     *     refresh_token_source: string|null,
     *     property_id_source: string|null,
     *     property_id_save_url: string|null
     * }
     */
    public function configurationStatus(): array
    {
        $hasProperty = filled($this->propertyId());
        $hasRefresh = filled($this->refreshToken())
            && filled($this->clientId())
            && filled($this->clientSecret());
        $hasStatic = filled(config('seo.ga.access_token'));
        $canConnect = filled($this->clientId()) && filled($this->clientSecret());

        return [
            'property_id' => $this->propertyId(),
            'has_property_id' => $hasProperty,
            'has_client_id' => filled($this->clientId()),
            'has_client_secret' => filled($this->clientSecret()),
            'has_refresh_token' => filled($this->refreshToken()),
            'has_static_access_token' => $hasStatic,
            'auth_mode' => $hasRefresh ? 'oauth_refresh' : ($hasStatic ? 'static_token' : 'missing'),
            'ready' => $hasProperty && ($hasRefresh || $hasStatic),
            'can_connect' => $canConnect,
            'connect_url' => $canConnect ? route('maintenance.ga.connect', absolute: false) : null,
            'disconnect_url' => $this->credentials->hasStoredRefreshToken()
                ? route('maintenance.ga.disconnect', absolute: false)
                : null,
            'refresh_token_source' => $this->refreshTokenSource(),
            'property_id_source' => $this->propertyIdSource(),
            'property_id_save_url' => route('maintenance.ga.property', absolute: false),
        ];
    }

    public function propertyId(): ?string
    {
        // Prefer admin-saved property ID so .env is optional.
        $stored = $this->credentials->getPropertyId();
        if (filled($stored)) {
            return $stored;
        }

        return $this->credentials->normalizePropertyId((string) config('seo.ga.property_id'));
    }

    public function propertyIdSource(): ?string
    {
        if ($this->credentials->hasStoredPropertyId()) {
            return 'database';
        }

        if ($this->credentials->normalizePropertyId((string) config('seo.ga.property_id')) !== null) {
            return 'env';
        }

        return null;
    }

    public function clientId(): ?string
    {
        $id = trim((string) config('seo.ga.client_id'));

        return $id !== '' ? $id : null;
    }

    public function clientSecret(): ?string
    {
        $secret = trim((string) config('seo.ga.client_secret'));

        return $secret !== '' ? $secret : null;
    }

    public function refreshToken(): ?string
    {
        // Prefer one-click Connect (DB) so a stale SEO_GA_REFRESH_TOKEN in .env cannot shadow it.
        $stored = $this->credentials->getRefreshToken();
        if (filled($stored)) {
            return $stored;
        }

        $env = trim((string) config('seo.ga.refresh_token'));

        return $env !== '' ? $env : null;
    }

    public function refreshTokenSource(): ?string
    {
        if ($this->credentials->hasStoredRefreshToken()) {
            return 'database';
        }

        if (trim((string) config('seo.ga.refresh_token')) !== '') {
            return 'env';
        }

        return null;
    }

    public function forgetCachedAccessToken(): void
    {
        Cache::forget($this->accessTokenCacheKey());
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runReport(array $body): array
    {
        return $this->postDataApi('runReport', $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runRealtimeReport(array $body): array
    {
        return $this->postDataApi('runRealtimeReport', $body);
    }

    /**
     * Compact realtime snapshot for admin UI (cached ~45s, locked against stampede).
     *
     * @return array{
     *     ready: bool,
     *     active_users: int,
     *     pages: list<array{path: string, users: int}>,
     *     countries: list<array{country: string, users: int}>,
     *     fetched_at: string|null,
     *     cached: bool,
     *     error: string|null,
     *     connect_url: string|null,
     *     status: array<string, mixed>
     * }
     */
    public function realtimeSnapshot(bool $force = false): array
    {
        $status = $this->configurationStatus();
        $base = [
            'ready' => (bool) $status['ready'],
            'active_users' => 0,
            'pages' => [],
            'countries' => [],
            'fetched_at' => null,
            'cached' => false,
            'error' => null,
            'connect_url' => $status['connect_url'],
            'status' => $status,
        ];

        if (! $status['ready']) {
            $base['error'] = 'Google Analytics is not connected yet.';

            return $base;
        }

        $propertyId = $this->propertyId() ?? 'unknown';
        $cacheKey = 'seo:ga:realtime:'.$propertyId;
        $forceKey = 'seo:ga:realtime:force:'.$propertyId;

        if (! $force) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['active_users'])) {
                return array_merge($base, $cached, ['cached' => true, 'ready' => true]);
            }
        } else {
            // Manual refresh at most once per 20s — still serve cache if cooling down.
            if (! Cache::add($forceKey, 1, now()->addSeconds(20))) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['active_users'])) {
                    return array_merge($base, $cached, ['cached' => true, 'ready' => true]);
                }
            }
        }

        $lock = Cache::lock('seo:ga:realtime:lock:'.$propertyId, 15);

        try {
            $acquired = $lock->block(3);
        } catch (\Throwable) {
            $acquired = false;
        }

        if (! $acquired) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['active_users'])) {
                return array_merge($base, $cached, ['cached' => true, 'ready' => true]);
            }

            return array_merge($base, [
                'error' => 'Google Analytics is busy. Try again in a moment.',
            ]);
        }

        try {
            // Re-check cache after winning the lock (another process may have filled it).
            if (! $force) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['active_users'])) {
                    return array_merge($base, $cached, ['cached' => true, 'ready' => true]);
                }
            }

            // Two calls only: headline active users + top pages (countries omitted to save quota).
            $activePayload = $this->runRealtimeReport([
                'metrics' => [['name' => 'activeUsers']],
            ]);
            $activeUsers = (int) ($activePayload['rows'][0]['metricValues'][0]['value'] ?? 0);

            $pagesPayload = $this->runRealtimeReport([
                'dimensions' => [['name' => 'unifiedPagePathScreen']],
                'metrics' => [['name' => 'activeUsers']],
                'limit' => 8,
                'orderBys' => [[
                    'metric' => ['metricName' => 'activeUsers'],
                    'desc' => true,
                ]],
            ]);

            $pages = [];
            foreach ($pagesPayload['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $path = trim((string) ($row['dimensionValues'][0]['value'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $pages[] = [
                    'path' => $path,
                    'users' => (int) ($row['metricValues'][0]['value'] ?? 0),
                ];
            }

            $snapshot = [
                'active_users' => $activeUsers,
                'pages' => $pages,
                'countries' => [],
                'fetched_at' => now()->toIso8601String(),
                'error' => null,
            ];

            Cache::put($cacheKey, $snapshot, now()->addSeconds(45));

            return array_merge($base, $snapshot, ['cached' => false, 'ready' => true]);
        } catch (\Throwable $e) {
            Log::warning('GA realtime snapshot failed', ['message' => $e->getMessage()]);

            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['active_users'])) {
                return array_merge($base, $cached, [
                    'cached' => true,
                    'ready' => true,
                    'error' => null,
                ]);
            }

            return array_merge($base, [
                'error' => $this->publicErrorMessage($e),
            ]);
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Lock may already be released or never owned.
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postDataApi(string $method, array $body): array
    {
        $propertyId = $this->propertyId();
        $token = $this->resolveAccessToken();

        if (! filled($propertyId) || ! filled($token)) {
            throw new RuntimeException('Google Analytics is not configured (property ID + OAuth/token).');
        }

        $endpoint = 'https://analyticsdata.googleapis.com/v1beta/properties/'
            .rawurlencode($propertyId)
            .':'.$method;

        $response = $this->postReport($endpoint, $token, $body);

        if ($response->status() === 401 && filled($this->refreshToken())) {
            Cache::forget($this->accessTokenCacheKey());
            $token = $this->resolveAccessToken(forceRefresh: true);
            if (! filled($token)) {
                throw new RuntimeException('GA OAuth refresh failed after 401.');
            }
            $response = $this->postReport($endpoint, $token, $body);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'GA '.$method.' HTTP '.$response->status().': '.Str::limit($response->body(), 300)
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function resolveAccessToken(bool $forceRefresh = false): ?string
    {
        if (filled($this->refreshToken()) && filled($this->clientId()) && filled($this->clientSecret())) {
            try {
                return $this->accessTokenFromRefresh($forceRefresh);
            } catch (\Throwable $e) {
                Log::warning('GA OAuth refresh failed', ['message' => $e->getMessage()]);
            }
        }

        $static = trim((string) config('seo.ga.access_token'));

        return $static !== '' ? $static : null;
    }

    private function postReport(string $endpoint, string $token, array $body): \Illuminate\Http\Client\Response
    {
        return Http::withToken($token)
            ->timeout(45)
            ->retry(
                2,
                300,
                fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException,
                false,
            )
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $body);
    }

    private function accessTokenFromRefresh(bool $forceRefresh = false): string
    {
        $cacheKey = $this->accessTokenCacheKey();

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = Http::asForm()
            ->timeout(20)
            ->retry(
                2,
                200,
                fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException,
                false,
            )
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $this->refreshToken(),
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'OAuth token refresh HTTP '.$response->status().': '.Str::limit($response->body(), 300)
            );
        }

        $accessToken = trim((string) $response->json('access_token'));
        if ($accessToken === '') {
            throw new RuntimeException('OAuth token refresh returned empty access_token.');
        }

        $expiresIn = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));

        return $accessToken;
    }

    private function accessTokenCacheKey(): string
    {
        return 'seo:ga:access_token:'.sha1((string) $this->clientId().'|'.(string) $this->refreshToken());
    }

    private function publicErrorMessage(\Throwable $e): string
    {
        $raw = strtolower($e->getMessage());

        if (str_contains($raw, '401') || str_contains($raw, 'invalid_grant') || str_contains($raw, 'oauth refresh failed')) {
            return 'Google Analytics auth expired. Reconnect under SEO & Learning.';
        }

        if (str_contains($raw, '403') || str_contains($raw, 'permission')) {
            return 'No access to this GA4 property. Check SEO_GA_PROPERTY_ID and Google account permissions.';
        }

        if (str_contains($raw, '404') || str_contains($raw, 'not found')) {
            return 'GA4 property not found. Check SEO_GA_PROPERTY_ID.';
        }

        if (str_contains($raw, '429') || str_contains($raw, 'quota') || str_contains($raw, 'rate')) {
            return 'Google Analytics rate limit hit. Wait a minute and retry.';
        }

        return 'Google Analytics temporarily unavailable.';
    }
}
