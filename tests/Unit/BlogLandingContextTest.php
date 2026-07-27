<?php

namespace Tests\Unit;

use App\Services\BlogAi\BlogContentAgent;
use App\Services\BlogAi\BlogLandingContextService;
use App\Services\BlogAi\BlogProductBriefBuilder;
use ReflectionMethod;
use Tests\TestCase;

class BlogLandingContextTest extends TestCase
{
    public function test_detects_cluster_from_bangla_and_english_needles(): void
    {
        $svc = app(BlogLandingContextService::class);

        $this->assertSame('fake_order', $svc->detectCluster('কিভাবে ফেক অর্ডার আটকাবো COD'));
        $this->assertSame('fraud_checker', $svc->detectCluster('pathao fraud check courier history'));
        $this->assertSame('courier', $svc->detectCluster('কুরিয়ার অটো এন্ট্রি Pathao'));
        $this->assertSame('courier', $svc->detectCluster('steadfast return request ask to return'));
        $this->assertSame('messenger', $svc->detectCluster('woocommerce facebook messenger inbox'));
        $this->assertSame('return_loss', $svc->detectCluster('রিটার্ন লস ক্যালকুলেটর মাসিক'));
        $this->assertSame('courier_charge', $svc->detectCluster('কুরিয়ার চার্জ ক্যালকুলেটর pathao চার্জ'));
        $this->assertSame('facebook_ads', $svc->detectCluster('Facebook Ads ROAS ফেক purchase'));
        $this->assertSame('general', $svc->detectCluster(''));
    }

    public function test_resolve_cluster_prefers_keyword_detection_over_blank_explicit(): void
    {
        $svc = app(BlogLandingContextService::class);

        $resolved = $svc->resolveCluster(
            null,
            'ফ্রি ফ্রড চেকার বাংলাদেশ',
            ['কুরিয়ার হিস্টোরি চেক', 'fraud checker'],
            [],
        );

        $this->assertSame('fraud_checker', $resolved['cluster']);
        $this->assertSame('/bd-fraud-checker', $resolved['landing']['primary_path']);
        $this->assertContains('/bd-fraud-checker', $resolved['landing']['must_link_paths']);
    }

    public function test_resolve_cluster_keeps_explicit_pick_over_keywords(): void
    {
        $svc = app(BlogLandingContextService::class);

        $resolved = $svc->resolveCluster(
            'courier',
            'কুরিয়ার হিস্টোরি ও ফ্রড চেকার',
            ['fraud checker', 'ফেক অর্ডার'],
            [],
        );

        $this->assertSame('courier', $resolved['cluster']);
        $this->assertSame('explicit', $resolved['source']);
        $this->assertSame('/steadfast-integration', $resolved['landing']['primary_path']);
        $this->assertContains('/courier-auto-entry', $resolved['landing']['must_link_paths']);
    }

    public function test_resolve_cluster_keeps_explicit_general(): void
    {
        $svc = app(BlogLandingContextService::class);

        $resolved = $svc->resolveCluster('general', '', [], [
            'next_post_ideas' => [['cluster' => 'fake_order', 'seed_topic' => 'x']],
        ]);

        $this->assertSame('general', $resolved['cluster']);
        $this->assertSame('explicit', $resolved['source']);
    }

    public function test_resolve_cluster_falls_back_to_learning_when_ambiguous(): void
    {
        $svc = app(BlogLandingContextService::class);

        $resolved = $svc->resolveCluster(
            null,
            '',
            [],
            [
                'next_post_ideas' => [
                    [
                        'cluster' => 'courier',
                        'seed_topic' => 'কুরিয়ার অটো এন্ট্রি বেনিফিট',
                    ],
                ],
            ],
        );

        $this->assertSame('courier', $resolved['cluster']);
        $this->assertSame('learning_idea', $resolved['source']);
        $this->assertSame('/steadfast-integration', $resolved['landing']['primary_path']);
    }

    public function test_product_brief_includes_cluster_landing_pages(): void
    {
        $brief = app(BlogProductBriefBuilder::class)->build('fake_order');

        $this->assertSame('fake_order', $brief['cluster']);
        $this->assertArrayHasKey('cluster_landing', $brief);
        $this->assertSame('/fake-order-protection', $brief['cluster_landing']['primary_path']);
        $this->assertNotEmpty($brief['cluster_landing']['pages']);
        $this->assertSame('/fake-order-protection', $brief['cluster_landing']['pages'][0]['path']);
        $this->assertNotEmpty($brief['cluster_landing']['pages'][0]['h1']);
        $this->assertNotEmpty($brief['seo_tools']);
        $this->assertTrue(collect($brief['seo_tools'])->contains(fn ($t) => ($t['path'] ?? null) === '/return-loss-calculator'));
    }

    public function test_product_brief_includes_product_truth_for_courier_and_messenger(): void
    {
        $courier = app(BlogProductBriefBuilder::class)->build('courier');
        $this->assertArrayHasKey('product_truth', $courier);
        $this->assertSame('plugin-1.5.4', $courier['product_truth']['version'] ?? null);
        $this->assertContains('steadfast', $courier['product_truth']['courier_hub']['partners_with_hub'] ?? []);
        $this->assertSame('/steadfast-integration', $courier['cluster_landing']['primary_path']);
        $this->assertContains('/steadfast-integration', $courier['cluster_landing']['must_link_paths']);
        $this->assertNotEmpty($courier['preferred_feature_themes']);

        $messenger = app(BlogProductBriefBuilder::class)->build('messenger');
        $this->assertTrue((bool) ($messenger['product_truth']['messenger']['human_inbox'] ?? false));
        $this->assertSame('/woocommerce-facebook-messenger', $messenger['cluster_landing']['primary_path']);
        $doNot = $messenger['product_truth']['do_not_claim'] ?? [];
        $this->assertTrue(collect($doNot)->contains(fn ($line) => str_contains((string) $line, 'Meta AI Bot')));
    }

    public function test_filter_valid_links_injects_required_cluster_landing_path(): void
    {
        /** @var BlogContentAgent $agent */
        $agent = $this->app->make(BlogContentAgent::class);
        $method = new ReflectionMethod(BlogContentAgent::class, 'filterValidLinks');
        $method->setAccessible(true);

        $links = $method->invoke($agent, [
            ['path' => '/pricing', 'anchor' => 'প্রাইসিং'],
            ['path' => '/blog/some-post', 'anchor' => 'old'],
        ], 'fraud_checker');

        $this->assertSame('/bd-fraud-checker', $links[0]['path']);
        $this->assertTrue(collect($links)->contains(fn ($l) => $l['path'] === '/pricing'));
    }

    public function test_filter_valid_links_injects_tool_must_links_for_return_loss(): void
    {
        /** @var BlogContentAgent $agent */
        $agent = $this->app->make(BlogContentAgent::class);
        $method = new ReflectionMethod(BlogContentAgent::class, 'filterValidLinks');
        $method->setAccessible(true);

        $links = $method->invoke($agent, [
            ['path' => '/pricing', 'anchor' => 'প্রাইসিং'],
        ], 'return_loss');

        $paths = collect($links)->pluck('path')->all();
        $this->assertSame('/return-loss-calculator', $paths[0]);
        $this->assertContains('/bd-fraud-checker', $paths);
        $this->assertStringContainsString('রিটার্ন', (string) $links[0]['anchor']);
    }

    public function test_facebook_ads_cluster_primary_is_roas_calculator(): void
    {
        $landing = app(BlogLandingContextService::class)->forCluster('facebook_ads');

        $this->assertSame('/ads-roas-calculator', $landing['primary_path']);
        $this->assertContains('/ads-roas-calculator', $landing['must_link_paths']);
    }
}
