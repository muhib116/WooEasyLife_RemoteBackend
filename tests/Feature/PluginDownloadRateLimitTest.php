<?php

namespace Tests\Feature;

use App\Models\PluginsVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase 1: plugin zip/metadata must not share the global api IP bucket.
 *
 * @group plugin-api
 */
class PluginDownloadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private string $zipRelativePath = 'app/private/wel-rate-limit-test.zip';

    private string $zipAbsolutePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate-limit hits persist across tests when using file/array cache.
        \Illuminate\Support\Facades\Cache::flush();
        RateLimiter::clear($this->downloadKey('127.0.0.1'));
        RateLimiter::clear('127.0.0.1');
        RateLimiter::clear('ip:127.0.0.1');

        $this->zipAbsolutePath = storage_path($this->zipRelativePath);
        $dir = dirname($this->zipAbsolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($this->zipAbsolutePath, 'PK'.str_repeat('0', 64));

        PluginsVersion::create([
            'version' => '9.9.9-test',
            'path' => $this->zipRelativePath,
            'download_count' => 0,
            'settings' => [
                'name' => 'WooEasyLife',
                'slug' => 'woo-easy-life',
                'version' => '9.9.9-test',
                'download_url' => 'https://app.wpsalehub.com/download-plugins',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->zipAbsolutePath) && is_file($this->zipAbsolutePath)) {
            @unlink($this->zipAbsolutePath);
        }

        parent::tearDown();
    }

    private function downloadKey(string $ip): string
    {
        // Illuminate prefixes named limiter keys; hit/tooManyAttempts use the same resolver path
        // via HTTP. Prefer HTTP assertions for the public contract.
        return 'plugin_download:'.$ip;
    }

    public function test_download_plugins_uses_dedicated_limiter_header(): void
    {
        $response = $this->get('/download-plugins');

        $response->assertOk();
        $this->assertSame(
            '60',
            (string) $response->headers->get('X-RateLimit-Limit'),
            'download-plugins must use plugin_download (60), not api (100)'
        );
        $this->assertNotSame('100', (string) $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_get_metadata_uses_dedicated_limiter_header(): void
    {
        $response = $this->get('/get-metadata');

        $response->assertOk();
        $this->assertSame(
            '120',
            (string) $response->headers->get('X-RateLimit-Limit'),
            'get-metadata must use plugin_metadata (120), not api (100)'
        );
    }

    public function test_download_plugins_is_rate_limited_and_not_unlimited(): void
    {
        RateLimiter::clear('plugin_download:'.sha1('127.0.0.1'));
        RateLimiter::for('plugin_download', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(3)->by(request()->ip());
        });

        $this->get('/download-plugins')->assertOk();
        $this->get('/download-plugins')->assertOk();
        $this->get('/download-plugins')->assertOk();
        $this->get('/download-plugins')->assertStatus(429);
    }

    public function test_api_bucket_hits_do_not_consume_download_budget(): void
    {
        RateLimiter::for('api', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(2)->by(request()->ip());
        });
        RateLimiter::for('plugin_download', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(request()->ip());
        });

        $this->get('/app-logo');
        $this->get('/app-logo');
        $this->get('/app-logo')->assertStatus(429);

        $this->get('/download-plugins')->assertOk();
    }
}
