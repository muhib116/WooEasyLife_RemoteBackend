<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google Search Console (Webmasters) API client.
 *
 * Prefers OAuth refresh-token flow using GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
 * (or SEO_GSC_* overrides). Refresh token may come from .env or one-click admin connect
 * (PlatformSetting). Falls back to a static SEO_GSC_ACCESS_TOKEN when set.
 */
class GoogleSearchConsoleClient
{
    public function __construct(
        private GscCredentialStore $credentials,
    ) {}

    public function configured(): bool
    {
        return filled($this->siteUrl()) && filled($this->resolveAccessToken());
    }

    /**
     * @return array{
     *     site_url: string|null,
     *     has_site_url: bool,
     *     has_client_id: bool,
     *     has_client_secret: bool,
     *     has_refresh_token: bool,
     *     has_static_access_token: bool,
     *     auth_mode: string,
     *     ready: bool,
     *     can_connect: bool,
     *     connect_url: string|null,
     *     disconnect_url: string|null,
     *     refresh_token_source: string|null
     * }
     */
    public function configurationStatus(): array
    {
        $hasSite = filled($this->siteUrl());
        $hasRefresh = filled($this->refreshToken())
            && filled($this->clientId())
            && filled($this->clientSecret());
        $hasStatic = filled(config('seo.gsc.access_token'));
        $canConnect = filled($this->clientId()) && filled($this->clientSecret());

        return [
            'site_url' => $this->siteUrl(),
            'has_site_url' => $hasSite,
            'has_client_id' => filled($this->clientId()),
            'has_client_secret' => filled($this->clientSecret()),
            'has_refresh_token' => filled($this->refreshToken()),
            'has_static_access_token' => $hasStatic,
            'auth_mode' => $hasRefresh ? 'oauth_refresh' : ($hasStatic ? 'static_token' : 'missing'),
            'ready' => $hasSite && ($hasRefresh || $hasStatic),
            'can_connect' => $canConnect,
            'connect_url' => $canConnect ? route('maintenance.gsc.connect', absolute: false) : null,
            'disconnect_url' => $this->credentials->hasStoredRefreshToken()
                ? route('maintenance.gsc.disconnect', absolute: false)
                : null,
            'refresh_token_source' => $this->refreshTokenSource(),
        ];
    }

    public function siteUrl(): ?string
    {
        $url = trim((string) config('seo.gsc.site_url'));
        if ($url === '') {
            return null;
        }

        // Domain properties must stay as sc-domain:example.com
        if (str_starts_with(strtolower($url), 'sc-domain:')) {
            return $url;
        }

        // URL-prefix properties in GSC usually include a trailing slash.
        if (preg_match('#^https?://#i', $url) === 1 && ! str_ends_with($url, '/')) {
            $url .= '/';
        }

        return $url;
    }

    public function clientId(): ?string
    {
        $id = trim((string) config('seo.gsc.client_id'));

        return $id !== '' ? $id : null;
    }

    public function clientSecret(): ?string
    {
        $secret = trim((string) config('seo.gsc.client_secret'));

        return $secret !== '' ? $secret : null;
    }

    public function refreshToken(): ?string
    {
        // Prefer one-click Connect (DB) so a stale SEO_GSC_REFRESH_TOKEN in .env cannot shadow it.
        $stored = $this->credentials->getRefreshToken();
        if (filled($stored)) {
            return $stored;
        }

        $env = trim((string) config('seo.gsc.refresh_token'));

        return $env !== '' ? $env : null;
    }

    public function refreshTokenSource(): ?string
    {
        if ($this->credentials->hasStoredRefreshToken()) {
            return 'database';
        }

        if (trim((string) config('seo.gsc.refresh_token')) !== '') {
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
     * @return array{rows?: list<array<string, mixed>>, responseAggregationType?: string}
     */
    public function searchAnalytics(array $body): array
    {
        $siteUrl = $this->siteUrl();
        $token = $this->resolveAccessToken();

        if (! filled($siteUrl) || ! filled($token)) {
            throw new RuntimeException('Google Search Console is not configured (site URL + OAuth/token).');
        }

        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
            .rawurlencode($siteUrl)
            .'/searchAnalytics/query';

        $response = $this->postAnalytics($endpoint, $token, $body);

        if ($response->status() === 401 && filled($this->refreshToken())) {
            Cache::forget($this->accessTokenCacheKey());
            $token = $this->resolveAccessToken(forceRefresh: true);
            if (! filled($token)) {
                throw new RuntimeException('GSC OAuth refresh failed after 401.');
            }
            $response = $this->postAnalytics($endpoint, $token, $body);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'GSC searchAnalytics HTTP '.$response->status().': '.Str::limit($response->body(), 300)
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
                Log::warning('GSC OAuth refresh failed', ['message' => $e->getMessage()]);
            }
        }

        $static = trim((string) config('seo.gsc.access_token'));

        return $static !== '' ? $static : null;
    }

    private function postAnalytics(string $endpoint, string $token, array $body): \Illuminate\Http\Client\Response
    {
        return Http::withToken($token)
            ->timeout(45)
            ->retry(2, 300)
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
            ->retry(2, 200)
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
        return 'seo:gsc:access_token:'.sha1((string) $this->clientId().'|'.(string) $this->refreshToken());
    }
}
