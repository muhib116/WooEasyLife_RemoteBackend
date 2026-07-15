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
        $this->assertSame('/courier-auto-entry', $resolved['landing']['primary_path']);
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
        $this->assertSame('/courier-auto-entry', $resolved['landing']['primary_path']);
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
}
