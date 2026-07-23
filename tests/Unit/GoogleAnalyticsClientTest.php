<?php

namespace Tests\Unit;

use App\Services\Seo\GaCredentialStore;
use App\Services\Seo\GoogleAnalyticsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAnalyticsClientTest extends TestCase
{
    public function test_configured_with_static_access_token(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => 'ya29.static',
            'seo.ga.refresh_token' => null,
            'seo.ga.client_id' => null,
            'seo.ga.client_secret' => null,
        ]);

        $client = app(GoogleAnalyticsClient::class);

        $this->assertTrue($client->configured());
        $this->assertSame('static_token', $client->configurationStatus()['auth_mode']);
    }

    public function test_oauth_refresh_then_run_report(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => 'refresh-xyz',
            'seo.ga.client_id' => 'client-id-from-env',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.from-refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'analyticsdata.googleapis.com/v1beta/properties/*' => Http::response([
                'rows' => [
                    [
                        'dimensionValues' => [['value' => '20260722']],
                        'metricValues' => [
                            ['value' => '42'],
                            ['value' => '31'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $client = app(GoogleAnalyticsClient::class);
        $this->assertTrue($client->configured());
        $this->assertSame('oauth_refresh', $client->configurationStatus()['auth_mode']);

        $payload = $client->runReport([
            'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']],
            'metrics' => [['name' => 'sessions']],
            'limit' => 1,
        ]);

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('42', $payload['rows'][0]['metricValues'][0]['value']);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return $request['client_id'] === 'client-id-from-env'
                    && $request['grant_type'] === 'refresh_token';
            }

            return str_contains($request->url(), '/properties/123456789:runReport')
                && $request->hasHeader('Authorization', 'Bearer ya29.from-refresh');
        });
    }

    public function test_falls_back_to_static_token_when_refresh_fails(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => 'ya29.fallback',
            'seo.ga.refresh_token' => 'bad-refresh',
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
            'analyticsdata.googleapis.com/v1beta/properties/*' => Http::response(['rows' => []], 200),
        ]);

        $client = app(GoogleAnalyticsClient::class);
        $this->assertSame('ya29.fallback', $client->resolveAccessToken());

        $payload = $client->runReport([
            'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']],
            'metrics' => [['name' => 'sessions']],
            'limit' => 1,
        ]);

        $this->assertSame([], $payload['rows'] ?? []);
    }

    public function test_property_id_normalizes_properties_prefix(): void
    {
        config([
            'seo.ga.property_id' => 'properties/987654321',
            'seo.ga.access_token' => 'ya29.static',
        ]);

        $client = app(GoogleAnalyticsClient::class);

        $this->assertSame('987654321', $client->propertyId());
    }

    public function test_measurement_id_is_not_mistaken_for_property_id(): void
    {
        $store = app(GaCredentialStore::class);

        $this->assertNull($store->normalizePropertyId('G-V3TDVR7ED9'));
        $this->assertTrue($store->isMeasurementId('G-V3TDVR7ED9'));
        $this->assertSame('G-V3TDVR7ED9', $store->normalizeMeasurementId('g-v3tdvr7ed9'));

        $resolved = app(GoogleAnalyticsClient::class)->resolvePropertyIdInput('G-V3TDVR7ED9');
        $this->assertNull($resolved['property_id']);
        $this->assertNotNull($resolved['error']);
        $this->assertStringContainsString('Measurement ID', $resolved['error']);
    }

    public function test_measurement_id_resolves_via_admin_api_when_oauth_ready(): void
    {
        config([
            'seo.ga.property_id' => null,
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => 'refresh-xyz',
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.from-refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'analyticsadmin.googleapis.com/v1beta/accountSummaries' => Http::response([
                'accountSummaries' => [[
                    'propertySummaries' => [
                        ['property' => 'properties/555666777'],
                    ],
                ]],
            ], 200),
            'analyticsadmin.googleapis.com/v1beta/properties/555666777/dataStreams' => Http::response([
                'dataStreams' => [[
                    'webStreamData' => [
                        'measurementId' => 'G-V3TDVR7ED9',
                    ],
                ]],
            ], 200),
        ]);

        $resolved = app(GoogleAnalyticsClient::class)->resolvePropertyIdInput('G-V3TDVR7ED9');

        $this->assertSame('555666777', $resolved['property_id']);
        $this->assertNull($resolved['error']);
        $this->assertTrue($resolved['from_measurement']);
    }

    public function test_reads_refresh_token_from_platform_setting_when_env_empty(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => null,
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        app(GaCredentialStore::class)->putRefreshToken('stored-refresh');

        $client = app(GoogleAnalyticsClient::class);

        $this->assertSame('stored-refresh', $client->refreshToken());
        $this->assertSame('database', $client->refreshTokenSource());
        $this->assertTrue($client->configurationStatus()['has_refresh_token']);
        $this->assertTrue($client->configurationStatus()['can_connect']);
        $this->assertNotEmpty($client->configurationStatus()['connect_url']);
    }

    public function test_realtime_snapshot_returns_active_users_and_pages(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => 'refresh-xyz',
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        Cache::flush();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.from-refresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'analyticsdata.googleapis.com/v1beta/properties/*' => Http::sequence()
                ->push([
                    'rows' => [[
                        'metricValues' => [['value' => '7']],
                    ]],
                ], 200)
                ->push([
                    'rows' => [[
                        'dimensionValues' => [['value' => '/blog/fraud-checker']],
                        'metricValues' => [['value' => '4']],
                    ]],
                ], 200),
        ]);

        $client = app(GoogleAnalyticsClient::class);
        $snapshot = $client->realtimeSnapshot(force: true);

        $this->assertTrue($snapshot['ready']);
        $this->assertSame(7, $snapshot['active_users']);
        $this->assertSame('/blog/fraud-checker', $snapshot['pages'][0]['path']);
        $this->assertSame(4, $snapshot['pages'][0]['users']);
        $this->assertSame([], $snapshot['countries']);
        $this->assertNull($snapshot['error']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), ':runRealtimeReport');
        });
    }

    public function test_realtime_snapshot_reports_not_ready_when_unconfigured(): void
    {
        config([
            'seo.ga.property_id' => null,
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => null,
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        $snapshot = app(GoogleAnalyticsClient::class)->realtimeSnapshot();

        $this->assertFalse($snapshot['ready']);
        $this->assertNotNull($snapshot['error']);
    }

    public function test_run_report_refreshes_access_token_after_401(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => null,
            'seo.ga.refresh_token' => 'refresh-xyz',
            'seo.ga.client_id' => 'client-id',
            'seo.ga.client_secret' => 'client-secret',
        ]);

        Cache::flush();
        Cache::put(
            'seo:ga:access_token:'.sha1('client-id|refresh-xyz'),
            'ya29.stale',
            now()->addHour()
        );

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.fresh',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'analyticsdata.googleapis.com/v1beta/properties/*' => Http::sequence()
                ->push(['error' => 'expired'], 401)
                ->push([
                    'rows' => [[
                        'metricValues' => [['value' => '9']],
                    ]],
                ], 200),
        ]);

        $payload = app(GoogleAnalyticsClient::class)->runReport([
            'metrics' => [['name' => 'sessions']],
            'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']],
        ]);

        $this->assertSame('9', $payload['rows'][0]['metricValues'][0]['value']);
    }

    public function test_realtime_errors_are_sanitized_for_clients(): void
    {
        config([
            'seo.ga.property_id' => '123456789',
            'seo.ga.access_token' => 'ya29.static',
            'seo.ga.refresh_token' => null,
            'seo.ga.client_id' => null,
            'seo.ga.client_secret' => null,
        ]);

        Cache::flush();

        Http::fake([
            'analyticsdata.googleapis.com/v1beta/properties/*' => Http::response([
                'error' => [
                    'message' => 'User does not have sufficient permissions for this property 999',
                    'status' => 'PERMISSION_DENIED',
                ],
            ], 403),
        ]);

        $snapshot = app(GoogleAnalyticsClient::class)->realtimeSnapshot(force: true);

        $this->assertFalse($snapshot['ready'] && $snapshot['error'] === null);
        $this->assertNotNull($snapshot['error']);
        $this->assertStringNotContainsString('PERMISSION_DENIED', $snapshot['error']);
        $this->assertStringNotContainsString('property 999', $snapshot['error']);
        $this->assertStringContainsString('SEO_GA_PROPERTY_ID', $snapshot['error']);
    }
}
