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
     *     property_id_save_url: string|null,
     *     measurement_id: string|null,
     *     measurement_id_source: string|null,
     *     measurement_enabled: bool,
     *     measurement_save_url: string|null,
     *     public_gtag_active: bool
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
        $measurementId = $this->configuredMeasurementId();

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
            'measurement_id' => $measurementId,
            'measurement_id_source' => $this->publicMeasurementIdSource(),
            'measurement_enabled' => $this->publicMeasurementEnabled(),
            'measurement_save_url' => route('maintenance.ga.measurement', absolute: false),
            'public_gtag_active' => filled($measurementId) && $this->publicMeasurementEnabled(),
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

    /**
     * Configured Measurement ID for admin UI (DB preferred, then env) — ignores enabled toggle.
     */
    public function configuredMeasurementId(): ?string
    {
        $stored = $this->credentials->getMeasurementId();
        if (filled($stored)) {
            return $stored;
        }

        return $this->credentials->normalizeMeasurementId((string) config('seo.ga.measurement_id', ''));
    }

    /**
     * Measurement ID used by public gtag.js (null when disabled or unset).
     */
    public function publicMeasurementId(): ?string
    {
        if (! $this->publicMeasurementEnabled()) {
            return null;
        }

        return $this->configuredMeasurementId();
    }

    public function publicMeasurementIdSource(): ?string
    {
        if ($this->credentials->hasStoredMeasurementId()) {
            return 'database';
        }

        if ($this->credentials->normalizeMeasurementId((string) config('seo.ga.measurement_id', '')) !== null) {
            return 'env';
        }

        return null;
    }

    /**
     * Admin can force off. Default on when unset (env/default Measurement ID may still apply).
     */
    public function publicMeasurementEnabled(): bool
    {
        $override = $this->credentials->getMeasurementEnabled();

        return $override ?? true;
    }

    /**
     * @return array{measurement_id: string|null, enabled: bool, error: string|null}
     */
    public function resolvePublicMeasurementInput(?string $raw, mixed $enabled = null): array
    {
        $raw = trim((string) $raw);
        $enabledBool = $enabled === null
            ? $this->publicMeasurementEnabled()
            : filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($enabledBool === null) {
            $enabledBool = $this->publicMeasurementEnabled();
        }

        if ($raw === '') {
            return [
                'measurement_id' => null,
                'enabled' => $enabledBool,
                'error' => null,
            ];
        }

        $normalized = $this->credentials->normalizeMeasurementId($raw);
        if ($normalized === null) {
            return [
                'measurement_id' => null,
                'enabled' => $enabledBool,
                'error' => 'Enter a valid GA4 Measurement ID (e.g. G-V3TDVR7ED9).',
            ];
        }

        return [
            'measurement_id' => $normalized,
            'enabled' => $enabledBool,
            'error' => null,
        ];
    }

    /**
     * Accept numeric property ID, properties/N, or Measurement ID (G-XXXX) when OAuth is connected.
     *
     * @return array{property_id: string|null, error: string|null, from_measurement: bool}
     */
    public function resolvePropertyIdInput(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return ['property_id' => null, 'error' => null, 'from_measurement' => false];
        }

        $numeric = $this->credentials->normalizePropertyId($raw);
        if ($numeric !== null) {
            return ['property_id' => $numeric, 'error' => null, 'from_measurement' => false];
        }

        $measurementId = $this->credentials->normalizeMeasurementId($raw);
        if ($measurementId === null) {
            return [
                'property_id' => null,
                'error' => 'Enter a numeric GA4 Property ID (e.g. 123456789), not the G-XXXX Measurement ID — or paste G-XXXX after connecting Google Analytics to auto-resolve.',
                'from_measurement' => false,
            ];
        }

        if (! filled($this->resolveAccessToken())) {
            return [
                'property_id' => null,
                'error' => 'G-XXXX is a Measurement ID. Connect Google Analytics first to auto-resolve it, or enter the numeric Property ID from Admin → Property details.',
                'from_measurement' => true,
            ];
        }

        try {
            $propertyId = $this->resolveMeasurementIdToPropertyId($measurementId);
        } catch (\Throwable $e) {
            Log::warning('GA measurement ID resolve failed', [
                'measurement_id' => $measurementId,
                'message' => $e->getMessage(),
            ]);

            return [
                'property_id' => null,
                'error' => $this->publicErrorMessage($e),
                'from_measurement' => true,
            ];
        }

        if ($propertyId === null) {
            return [
                'property_id' => null,
                'error' => "No GA4 property found for {$measurementId}. Check the Google account used for Connect, or paste the numeric Property ID instead.",
                'from_measurement' => true,
            ];
        }

        return ['property_id' => $propertyId, 'error' => null, 'from_measurement' => true];
    }

    /**
     * Resolve Measurement ID (G-XXXX) → numeric property ID via Analytics Admin API.
     */
    public function resolveMeasurementIdToPropertyId(string $measurementId): ?string
    {
        $measurementId = $this->credentials->normalizeMeasurementId($measurementId);
        if ($measurementId === null) {
            return null;
        }

        $cacheKey = 'seo:ga:measurement:'.$measurementId;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && preg_match('/^\d{6,12}$/', $cached) === 1) {
            return $cached;
        }

        $token = $this->resolveAccessToken();
        if (! filled($token)) {
            throw new RuntimeException('Google Analytics OAuth is required to resolve Measurement IDs.');
        }

        $summaries = $this->getAdminApi('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', $token);
        $propertyIds = [];
        foreach ($summaries['accountSummaries'] ?? [] as $account) {
            if (! is_array($account)) {
                continue;
            }
            foreach ($account['propertySummaries'] ?? [] as $property) {
                if (! is_array($property)) {
                    continue;
                }
                $name = (string) ($property['property'] ?? '');
                if (preg_match('#properties/(\d+)#', $name, $m) === 1) {
                    $propertyIds[] = $m[1];
                }
            }
        }

        foreach (array_unique($propertyIds) as $propertyId) {
            $streams = $this->getAdminApi(
                'https://analyticsadmin.googleapis.com/v1beta/properties/'.$propertyId.'/dataStreams',
                $token,
            );
            foreach ($streams['dataStreams'] ?? [] as $stream) {
                if (! is_array($stream)) {
                    continue;
                }
                $found = strtoupper((string) (
                    $stream['webStreamData']['measurementId']
                    ?? $stream['measurementId']
                    ?? ''
                ));
                if ($found === $measurementId) {
                    Cache::put($cacheKey, $propertyId, now()->addDay());

                    return $propertyId;
                }
            }
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

    private function getAdminApi(string $endpoint, string $token): array
    {
        $response = Http::withToken($token)
            ->timeout(30)
            ->retry(
                2,
                300,
                fn ($exception) => $exception instanceof \Illuminate\Http\Client\ConnectionException,
                false,
            )
            ->acceptJson()
            ->get($endpoint);

        if ($response->status() === 401 && filled($this->refreshToken())) {
            Cache::forget($this->accessTokenCacheKey());
            $token = $this->resolveAccessToken(forceRefresh: true);
            if (! filled($token)) {
                throw new RuntimeException('GA OAuth refresh failed after 401.');
            }
            $response = Http::withToken($token)
                ->timeout(30)
                ->acceptJson()
                ->get($endpoint);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'GA Admin API HTTP '.$response->status().': '.Str::limit($response->body(), 300)
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
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
