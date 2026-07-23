<?php

namespace Tests\Unit;

use App\Services\Seo\GoogleSearchConsoleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleSearchConsoleClientTest extends TestCase
{
    public function test_configured_with_static_access_token(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'ya29.static',
            'seo.gsc.refresh_token' => null,
            'seo.gsc.client_id' => null,
            'seo.gsc.client_secret' => null,
        ]);

        $client = app(GoogleSearchConsoleClient::class);

        $this->assertTrue($client->configured());
        $this->assertSame('static_token', $client->configurationStatus()['auth_mode']);
    }

    public function test_oauth_refresh_then_search_analytics(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => null,
            'seo.gsc.refresh_token' => 'refresh-xyz',
            'seo.gsc.client_id' => 'client-id-from-env',
            'seo.gsc.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.from-refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'rows' => [
                    [
                        'keys' => ['ফ্রড চেকার'],
                        'clicks' => 12,
                        'impressions' => 400,
                        'ctr' => 0.03,
                        'position' => 8.2,
                    ],
                ],
            ], 200),
        ]);

        $client = app(GoogleSearchConsoleClient::class);
        $this->assertTrue($client->configured());
        $this->assertSame('oauth_refresh', $client->configurationStatus()['auth_mode']);

        $payload = $client->searchAnalytics([
            'startDate' => '2026-07-01',
            'endDate' => '2026-07-10',
            'dimensions' => ['query'],
            'rowLimit' => 5,
        ]);

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('ফ্রড চেকার', $payload['rows'][0]['keys'][0]);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return $request['client_id'] === 'client-id-from-env'
                    && $request['grant_type'] === 'refresh_token';
            }

            return str_contains($request->url(), '/searchAnalytics/query')
                && $request->hasHeader('Authorization', 'Bearer ya29.from-refresh');
        });
    }

    public function test_search_analytics_refreshes_access_token_after_401(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => null,
            'seo.gsc.refresh_token' => 'refresh-xyz',
            'seo.gsc.client_id' => 'client-id',
            'seo.gsc.client_secret' => 'client-secret',
        ]);

        Cache::flush();
        Cache::put(
            'seo:gsc:access_token:'.sha1('client-id|refresh-xyz'),
            'ya29.stale',
            now()->addHour()
        );

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.fresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::sequence()
                ->push(['error' => 'expired'], 401)
                ->push(['rows' => [['keys' => ['ok'], 'clicks' => 1]]], 200),
        ]);

        $payload = app(GoogleSearchConsoleClient::class)->searchAnalytics([
            'startDate' => '2026-07-01',
            'endDate' => '2026-07-10',
            'dimensions' => ['query'],
            'rowLimit' => 1,
        ]);

        $this->assertSame('ok', $payload['rows'][0]['keys'][0]);
    }

    public function test_falls_back_to_static_token_when_refresh_fails(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'ya29.fallback',
            'seo.gsc.refresh_token' => 'bad-refresh',
            'seo.gsc.client_id' => 'client-id',
            'seo.gsc.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response(['rows' => []], 200),
        ]);

        $client = app(GoogleSearchConsoleClient::class);
        $this->assertSame('ya29.fallback', $client->resolveAccessToken());

        $payload = $client->searchAnalytics([
            'startDate' => '2026-07-01',
            'endDate' => '2026-07-10',
            'dimensions' => ['query'],
            'rowLimit' => 1,
        ]);

        $this->assertSame([], $payload['rows'] ?? []);
    }

    public function test_client_id_reads_from_seo_gsc_config(): void
    {
        config([
            'seo.gsc.client_id' => '850684885644-example.apps.googleusercontent.com',
            'seo.gsc.client_secret' => 'secret',
            'seo.gsc.refresh_token' => 'refresh',
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => null,
        ]);

        $client = app(GoogleSearchConsoleClient::class);

        $this->assertSame('850684885644-example.apps.googleusercontent.com', $client->clientId());
        $this->assertTrue($client->configurationStatus()['has_client_id']);
        $this->assertSame('oauth_refresh', $client->configurationStatus()['auth_mode']);
    }

    public function test_reads_refresh_token_from_platform_setting_when_env_empty(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => null,
            'seo.gsc.refresh_token' => null,
            'seo.gsc.client_id' => 'client-id',
            'seo.gsc.client_secret' => 'client-secret',
        ]);

        app(\App\Services\Seo\GscCredentialStore::class)->putRefreshToken('stored-refresh');

        $client = app(GoogleSearchConsoleClient::class);

        $this->assertSame('stored-refresh', $client->refreshToken());
        $this->assertSame('database', $client->refreshTokenSource());
        $this->assertTrue($client->configurationStatus()['has_refresh_token']);
        $this->assertTrue($client->configurationStatus()['can_connect']);
        $this->assertNotEmpty($client->configurationStatus()['connect_url']);
    }

    public function test_site_url_normalizes_trailing_slash_for_url_prefix_properties(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com',
            'seo.gsc.access_token' => 'ya29.static',
        ]);

        $client = app(GoogleSearchConsoleClient::class);

        $this->assertSame('https://example.com/', $client->siteUrl());
    }

    public function test_site_url_keeps_sc_domain_property_form(): void
    {
        config([
            'seo.gsc.site_url' => 'sc-domain:example.com',
            'seo.gsc.access_token' => 'ya29.static',
        ]);

        $client = app(GoogleSearchConsoleClient::class);

        $this->assertSame('sc-domain:example.com', $client->siteUrl());
    }
}
