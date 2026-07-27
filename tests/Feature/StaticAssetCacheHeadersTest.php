<?php

namespace Tests\Feature;

use Tests\TestCase;

class StaticAssetCacheHeadersTest extends TestCase
{
    public function test_htaccess_enables_browser_caching_for_js_and_css(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('mod_expires', $htaccess);
        $this->assertStringContainsString('mod_headers', $htaccess);
        $this->assertStringContainsString('max-age=31536000, immutable', $htaccess);
        $this->assertStringContainsString('build/assets/', $htaccess);
        $this->assertStringContainsString('ExpiresByType text/css', $htaccess);
        $this->assertStringContainsString('ExpiresByType application/javascript', $htaccess);
    }

    public function test_vite_build_assets_are_served_with_long_cache_headers(): void
    {
        $assetsDir = public_path('build/assets');
        $this->assertDirectoryExists($assetsDir);

        $js = collect(glob($assetsDir.'/*.js') ?: [])->first();
        $css = collect(glob($assetsDir.'/*.css') ?: [])->first();

        $this->assertNotNull($js, 'Expected at least one built JS asset in public/build/assets');
        $this->assertNotNull($css, 'Expected at least one built CSS asset in public/build/assets');

        foreach ([$js, $css] as $file) {
            $path = '/build/assets/'.basename((string) $file);
            $response = $this->get($path);

            $response->assertOk();

            $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('public', $cacheControl);
            $this->assertStringContainsString('max-age=31536000', $cacheControl);
            $this->assertStringContainsString('immutable', $cacheControl);
            $this->assertNotEmpty($response->headers->get('Expires'));
        }
    }

    public function test_public_marketing_pages_render_ok(): void
    {
        $pages = [
            '/',
            '/fake-order-protection',
            '/en/fake-order-protection',
            '/courier-auto-entry',
            '/en/courier-auto-entry',
            '/steadfast-return-hub',
            '/en/steadfast-return-hub',
            '/woocommerce-facebook-messenger',
            '/en/woocommerce-facebook-messenger',
            '/return-loss-calculator',
            '/en/return-loss-calculator',
            '/bd-fraud-checker',
            '/pricing',
        ];

        foreach ($pages as $page) {
            $this->get($page)->assertOk();
        }
    }
}
