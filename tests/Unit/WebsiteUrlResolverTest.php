<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteBaseUrlNormalizer;
use App\Services\WebsiteUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteUrlResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_website_base_url_when_set(): void
    {
        $website = Website::create([
            'user_id' => User::create([
                'name' => 'Merchant',
                'email' => 'merchant@example.com',
                'phone' => '01700000000',
                'password' => Hash::make('password'),
                'role' => 'user',
                'status' => true,
            ])->id,
            'domain' => 'localhost',
            'base_url' => 'http://localhost:8081/wordpress',
            'title' => 'Local WordPress',
            'status' => true,
        ]);

        $candidates = app(WebsiteUrlResolver::class)->siteUrlCandidates($website);

        $this->assertSame(['http://localhost:8081/wordpress'], $candidates);
    }

    public function test_falls_back_to_https_domain_when_base_url_missing(): void
    {
        $website = Website::create([
            'user_id' => User::create([
                'name' => 'Merchant',
                'email' => 'shop@example.com',
                'phone' => '01700000001',
                'password' => Hash::make('password'),
                'role' => 'user',
                'status' => true,
            ])->id,
            'domain' => 'shop.example.com',
            'title' => 'Shop',
            'status' => true,
        ]);

        $candidates = app(WebsiteUrlResolver::class)->siteUrlCandidates($website);

        $this->assertSame(['https://shop.example.com'], $candidates);
    }

    public function test_base_url_normalizer_strips_trailing_slash_and_preserves_path(): void
    {
        $normalizer = app(WebsiteBaseUrlNormalizer::class);

        $this->assertSame(
            'http://localhost:8081/wordpress',
            $normalizer->normalize('http://localhost:8081/wordpress/')
        );
    }

    public function test_base_url_normalizer_rejects_hostname_only_values(): void
    {
        $normalizer = app(WebsiteBaseUrlNormalizer::class);

        $this->assertNull($normalizer->normalize('localhost'));
        $this->assertNull($normalizer->normalize('shop.example.com'));
    }

    public function test_base_url_normalizer_requires_matching_store_domain_host(): void
    {
        $normalizer = app(WebsiteBaseUrlNormalizer::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $normalizer->normalizeForDomain('http://evil.example.com/wordpress', 'localhost');
    }

    public function test_base_url_normalizer_allows_port_and_path_for_matching_host(): void
    {
        $normalizer = app(WebsiteBaseUrlNormalizer::class);

        $this->assertSame(
            'http://localhost:8081/wordpress',
            $normalizer->normalizeForDomain('http://localhost:8081/wordpress/', 'localhost')
        );
    }

    public function test_includes_token_domain_when_it_differs_from_website_domain(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'legacy@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'title' => 'Shop',
            'status' => true,
        ]);

        AccessToken::unguarded(function () use ($merchant, $website) {
            $token = AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Token',
                'token' => hash('sha256', 'plain-token'),
                'domain' => 'legacy.example.com',
                'website_id' => $website->id,
                'status' => true,
            ]);

            $candidates = app(WebsiteUrlResolver::class)->siteUrlCandidates($website, $token);

            $this->assertContains('https://shop.example.com', $candidates);
            $this->assertContains('https://legacy.example.com', $candidates);
        });
    }
}
