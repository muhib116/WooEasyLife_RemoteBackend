<?php

namespace Tests\Feature;

use App\Services\BlogAi\BlogLandingContextService;
use App\Services\BlogAi\BlogLandingPageReferenceService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogLandingPageReferenceTest extends TestCase
{
    public function test_for_cluster_includes_primary_url_and_reference(): void
    {
        config([
            'app.url' => 'https://wooeasylife.com',
            'blog_ai.landing_reference.public_base_url' => 'https://wooeasylife.com',
            'blog_ai.landing_reference.fetch_live' => false,
        ]);

        $landing = app(BlogLandingContextService::class)->forCluster('fraud_checker');

        $this->assertSame('/bd-fraud-checker', $landing['primary_path']);
        $this->assertSame('https://wooeasylife.com/bd-fraud-checker', $landing['primary_url']);
        $this->assertIsArray($landing['landing_page_reference'] ?? null);
        $this->assertSame(
            'https://wooeasylife.com/bd-fraud-checker',
            $landing['landing_page_reference']['primary_url']
        );
        $this->assertSame('content_source_of_truth', $landing['landing_page_reference']['role']);
        $this->assertNotEmpty($landing['pages'][0]['h1'] ?? null);
    }

    public function test_live_snapshot_fetches_allowed_own_host(): void
    {
        config([
            'blog_ai.landing_reference.fetch_live' => true,
            'blog_ai.landing_reference.fetch_live_in_tests' => true,
            'blog_ai.landing_reference.public_base_url' => 'https://wooeasylife.com',
            'blog_ai.landing_reference.allowed_hosts' => ['wooeasylife.com'],
        ]);

        Http::fake([
            'https://wooeasylife.com/bd-fraud-checker' => Http::response(
                '<html><head><title>Fraud Checker</title></head>'
                .'<body><h1>ফ্রি ফ্রড চেকার</h1><h2>কীভাবে কাজ করে</h2>'
                .'<p>'.str_repeat('চেক করুন। ', 40).'</p></body></html>',
                200
            ),
        ]);

        $snap = app(BlogLandingPageReferenceService::class)
            ->fetchOwnLandingSnapshot('https://wooeasylife.com/bd-fraud-checker');

        $this->assertNotNull($snap);
        $this->assertSame('live_fetch', $snap['source']);
        $this->assertSame('ফ্রি ফ্রড চেকার', $snap['h1']);
        $this->assertNotEmpty($snap['headings']);
    }

    public function test_blocks_non_own_hosts(): void
    {
        config([
            'blog_ai.landing_reference.allowed_hosts' => ['wooeasylife.com'],
        ]);

        $snap = app(BlogLandingPageReferenceService::class)
            ->fetchOwnLandingSnapshot('https://evil.example/steal');

        $this->assertSame('blocked_host', $snap['source'] ?? null);
    }
}
