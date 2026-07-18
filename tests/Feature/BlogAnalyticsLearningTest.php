<?php

namespace Tests\Feature;

use App\Models\BlogContentEvent;
use App\Models\BlogLearningInsight;
use App\Models\BlogPost;
use App\Models\BlogPostAnalytics;
use App\Services\BlogAi\BlogLearningService;
use App\Services\BlogAi\BlogProductBriefBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAnalyticsLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_blog_view_event_is_recorded(): void
    {
        BlogPost::create([
            'title' => 'Fake Order Guide',
            'slug' => 'fake-order-guide',
            'locale' => 'bn',
            'status' => 'published',
            'cluster' => 'fake_order',
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p>Hello <a href="/">x</a></p>',
            'published_at' => now(),
        ]);

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-guide',
            'event' => 'view',
            'visitor_id' => str_repeat('ab', 16),
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('blog_content_events', [
            'slug' => 'fake-order-guide',
            'event_type' => BlogContentEvent::TYPE_VIEW,
        ]);

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-guide',
            'event' => 'scroll_depth',
            'scroll_pct' => 50,
            'visitor_id' => str_repeat('ab', 16),
        ])->assertOk();

        $this->assertDatabaseHas('blog_content_events', [
            'slug' => 'fake-order-guide',
            'event_type' => BlogContentEvent::TYPE_SCROLL,
        ]);
    }

    public function test_view_events_are_deduped_per_session_day(): void
    {
        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-guide',
            'event' => 'view',
            'visitor_id' => str_repeat('cd', 16),
        ])->assertOk();

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-guide',
            'event' => 'view',
            'visitor_id' => str_repeat('cd', 16),
        ])->assertOk()->assertJsonPath('ok', false);

        $this->assertSame(1, BlogContentEvent::query()->where('event_type', 'view')->count());
    }

    public function test_cta_click_and_learning_insights_build(): void
    {
        BlogPost::create([
            'title' => 'ফেক অর্ডার আটকানোর উপায়',
            'slug' => 'fake-order-atkabo',
            'locale' => 'bn',
            'status' => 'published',
            'cluster' => 'fake_order',
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p><a href="/bd-fraud-checker">x</a></p>',
            'published_at' => now(),
        ]);

        BlogPost::create([
            'title' => 'কুরিয়ার অটো এন্ট্রি গাইড',
            'slug' => 'courier-auto-entry-guide',
            'locale' => 'bn',
            'status' => 'published',
            'cluster' => 'courier',
            'focus_keyword' => 'কুরিয়ার অটো এন্ট্রি',
            'body_html' => '<p><a href="/">home</a></p>',
            'published_at' => now(),
        ]);

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-atkabo',
            'event' => 'view',
            'visitor_id' => str_repeat('11', 16),
        ])->assertOk();

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'fake-order-atkabo',
            'event' => 'cta_click',
            'cta_label' => 'ফ্রি ফ্রড চেক',
            'visitor_id' => str_repeat('11', 16),
        ])->assertOk();

        $this->postJson(route('blog.analytics.event'), [
            'slug' => 'courier-auto-entry-guide',
            'event' => 'view',
            'visitor_id' => str_repeat('22', 16),
        ])->assertOk();

        $this->artisan('blog:build-learning-insights')->assertSuccessful();

        $insight = BlogLearningInsight::latestGlobal();
        $this->assertNotNull($insight);
        $this->assertNotEmpty($insight->payload_json['recommended_clusters'] ?? []);
        $this->assertNotEmpty($insight->payload_json['next_post_ideas'] ?? []);

        $learning = app(BlogLearningService::class)->promptLearningBlock();
        $this->assertSame('ready', $learning['status']);
        $this->assertArrayHasKey('winning_keywords', $learning);
        $this->assertArrayHasKey('next_post_ideas', $learning);

        $row = BlogPostAnalytics::query()->where('slug', 'fake-order-atkabo')->first();
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row->engagement_score);
    }

    public function test_product_brief_includes_performance_learning(): void
    {
        BlogLearningInsight::query()->create([
            'scope' => 'global',
            'payload_json' => [
                'recommended_clusters' => ['fraud_checker'],
                'winning_keywords' => ['ফ্রড চেকার'],
                'winning_titles' => [],
                'underperforming_topics' => [],
                'coverage_gaps' => ['facebook_ads'],
                'writing_guidance' => ['Prefer practical BD seller angles'],
                'cta_labels_that_convert' => ['ফ্রি ফ্রড চেক' => 3],
            ],
            'summary_bn' => 'টেস্ট লার্নিং সামারি',
            'posts_analyzed' => 2,
            'events_analyzed' => 10,
            'generated_at' => now(),
        ]);

        $brief = app(BlogProductBriefBuilder::class)->build();
        $this->assertArrayHasKey('performance_learning', $brief);
        $this->assertSame('ready', $brief['performance_learning']['status']);
        $this->assertSame('টেস্ট লার্নিং সামারি', $brief['performance_learning']['summary_bn']);
    }

    public function test_rank_opportunity_classifier_buckets_striking_distance(): void
    {
        $learning = app(BlogLearningService::class);

        $result = $learning->classifyRankOpportunity(
            impressions: 120,
            clicks: 3,
            ctr: 0.025,
            position: 12.0,
        );

        $this->assertSame('striking_distance', $result['bucket']);
        $this->assertGreaterThan(0, $result['score']);
        $this->assertNotEmpty($result['hint']);
    }

    public function test_gsc_query_sync_stores_opportunities_and_admin_payload(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'rows' => [
                    [
                        'keys' => ['ফ্রড চেকার', 'https://example.com/blog/fake-order-guide'],
                        'clicks' => 4,
                        'impressions' => 200,
                        'ctr' => 0.02,
                        'position' => 11.5,
                    ],
                    [
                        'keys' => ['fake order check', 'https://example.com/blog/fake-order-guide'],
                        'clicks' => 40,
                        'impressions' => 500,
                        'ctr' => 0.02,
                        'position' => 2.0,
                    ],
                ],
            ], 200),
        ]);

        $learning = app(BlogLearningService::class);
        $sync = $learning->syncGscQueryMetrics();

        $this->assertSame(2, $sync['synced']);
        $this->assertDatabaseCount('blog_gsc_query_metrics', 2);
        $this->assertDatabaseHas('blog_gsc_query_metrics', [
            'slug' => 'fake-order-guide',
            'bucket' => 'striking_distance',
        ]);

        $admin = $learning->rankOpportunitiesForAdmin();
        $this->assertTrue($admin['configured']);
        $this->assertTrue($admin['table_ready']);
        $this->assertNotEmpty($admin['items']);

        $dashboard = $learning->adminDashboard();
        $this->assertArrayHasKey('rank_opportunities', $dashboard);
        $this->assertNotEmpty($dashboard['rank_opportunities']['items']);
    }

    public function test_gsc_query_sync_skips_without_credentials(): void
    {
        config([
            'seo.gsc.site_url' => null,
            'seo.gsc.access_token' => null,
        ]);

        $result = app(BlogLearningService::class)->syncGscQueryMetrics();

        $this->assertTrue($result['skipped'] ?? false);
        $this->assertSame(0, $result['synced']);
    }

    public function test_gsc_query_sync_keeps_existing_on_empty_api_response(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        \App\Models\BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'keep-me|https://example.com/blog/old'),
            'query' => 'keep-me',
            'page_url' => 'https://example.com/blog/old',
            'slug' => 'old',
            'clicks_28d' => 1,
            'impressions_28d' => 50,
            'ctr_28d' => 0.02,
            'position_28d' => 12,
            'bucket' => 'striking_distance',
            'opportunity_score' => 10,
            'improvement_hint' => 'keep',
            'metrics_refreshed_at' => now()->subDay(),
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['rows' => []], 200),
        ]);

        $result = app(BlogLearningService::class)->syncGscQueryMetrics();

        $this->assertTrue($result['kept_existing'] ?? false);
        $this->assertSame(0, $result['synced']);
        $this->assertDatabaseCount('blog_gsc_query_metrics', 1);
        $this->assertDatabaseHas('blog_gsc_query_metrics', ['query' => 'keep-me']);
    }

    public function test_gsc_query_sync_keeps_existing_when_all_rows_unusable(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        \App\Models\BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'keep-me|https://example.com/blog/old'),
            'query' => 'keep-me',
            'page_url' => 'https://example.com/blog/old',
            'slug' => 'old',
            'clicks_28d' => 1,
            'impressions_28d' => 50,
            'ctr_28d' => 0.02,
            'position_28d' => 12,
            'bucket' => 'striking_distance',
            'opportunity_score' => 10,
            'improvement_hint' => 'keep',
            'metrics_refreshed_at' => now()->subDay(),
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'rows' => [
                    ['keys' => ['', ''], 'clicks' => 1, 'impressions' => 10],
                    ['keys' => ['only-query'], 'clicks' => 1, 'impressions' => 10],
                ],
            ], 200),
        ]);

        $result = app(BlogLearningService::class)->syncGscQueryMetrics();

        $this->assertTrue($result['kept_existing'] ?? false);
        $this->assertDatabaseCount('blog_gsc_query_metrics', 1);
        $this->assertDatabaseHas('blog_gsc_query_metrics', ['query' => 'keep-me']);
    }

    public function test_gsc_query_sync_paginates_beyond_first_page(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        $page1 = [];
        for ($i = 0; $i < 1000; $i++) {
            $page1[] = [
                'keys' => ["query-{$i}", "https://example.com/blog/post-{$i}"],
                'clicks' => 2,
                'impressions' => 80,
                'ctr' => 0.025,
                'position' => 12.0,
            ];
        }

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
                ->push(['rows' => $page1], 200)
                ->push(['rows' => [
                    [
                        'keys' => ['extra-query', 'https://example.com/blog/extra'],
                        'clicks' => 3,
                        'impressions' => 90,
                        'ctr' => 0.033,
                        'position' => 10.0,
                    ],
                ]], 200),
        ]);

        $result = app(BlogLearningService::class)->syncGscQueryMetrics();

        $this->assertSame(1001, $result['synced']);
        $this->assertSame(2, $result['pages'] ?? null);
        $this->assertDatabaseCount('blog_gsc_query_metrics', 1001);
        $this->assertDatabaseHas('blog_gsc_query_metrics', ['slug' => 'extra']);
    }

    public function test_gsc_query_sync_keeps_existing_when_later_page_fails(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        \App\Models\BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'keep-me|https://example.com/blog/old'),
            'query' => 'keep-me',
            'page_url' => 'https://example.com/blog/old',
            'slug' => 'old',
            'clicks_28d' => 1,
            'impressions_28d' => 50,
            'ctr_28d' => 0.02,
            'position_28d' => 12,
            'bucket' => 'striking_distance',
            'opportunity_score' => 10,
            'improvement_hint' => 'keep',
            'metrics_refreshed_at' => now()->subDay(),
        ]);

        $page1 = [];
        for ($i = 0; $i < 1000; $i++) {
            $page1[] = [
                'keys' => ["query-{$i}", "https://example.com/blog/post-{$i}"],
                'clicks' => 2,
                'impressions' => 80,
                'ctr' => 0.025,
                'position' => 12.0,
            ];
        }

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
                ->push(['rows' => $page1], 200)
                ->push('rate limited', 429),
        ]);

        $result = app(BlogLearningService::class)->syncGscQueryMetrics();

        $this->assertTrue($result['kept_existing'] ?? false);
        $this->assertSame(0, $result['synced']);
        $this->assertDatabaseCount('blog_gsc_query_metrics', 1);
        $this->assertDatabaseHas('blog_gsc_query_metrics', ['query' => 'keep-me']);
    }

    public function test_gsc_page_sync_paginates_beyond_first_100_rows(): void
    {
        config([
            'seo.gsc.site_url' => 'https://example.com/',
            'seo.gsc.access_token' => 'test-token',
        ]);

        $page1 = [];
        for ($i = 0; $i < 100; $i++) {
            $page1[] = [
                'keys' => ["https://example.com/blog/page-{$i}"],
                'clicks' => 3,
                'impressions' => 90,
                'ctr' => 0.033,
                'position' => 8.0,
            ];
        }

        \Illuminate\Support\Facades\Http::fake([
            'www.googleapis.com/*' => \Illuminate\Support\Facades\Http::sequence()
                ->push(['rows' => $page1], 200)
                ->push(['rows' => [[
                    'keys' => ['https://example.com/blog/page-extra'],
                    'clicks' => 5,
                    'impressions' => 100,
                    'ctr' => 0.05,
                    'position' => 4.0,
                ]]], 200),
        ]);

        $result = app(BlogLearningService::class)->syncGscPageMetrics();

        $this->assertSame(101, $result['synced']);
        $this->assertSame(2, $result['pages'] ?? null);
        $this->assertDatabaseHas('blog_post_analytics', [
            'slug' => 'page-extra',
            'gsc_clicks_28d' => 5,
        ]);
    }

    public function test_learning_insights_prioritize_gsc_keyword_seeds(): void
    {
        \App\Models\BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'ফ্রড চেকার|https://example.com/blog/fraud'),
            'query' => 'ফ্রড চেকার',
            'page_url' => 'https://example.com/blog/fraud',
            'slug' => 'fraud',
            'clicks_28d' => 5,
            'impressions_28d' => 400,
            'ctr_28d' => 0.0125,
            'position_28d' => 11.2,
            'bucket' => 'striking_distance',
            'opportunity_score' => 88,
            'improvement_hint' => 'Add keyword to H1/title',
            'metrics_refreshed_at' => now(),
        ]);

        $learning = app(BlogLearningService::class);
        $seeds = $learning->gscKeywordSeeds(5);
        $this->assertNotEmpty($seeds);
        $this->assertSame('ফ্রড চেকার', $seeds[0]['query']);

        $insight = $learning->buildInsights();
        $payload = $insight->payload_json;
        $this->assertNotEmpty($payload['gsc_keyword_seeds'] ?? []);
        $this->assertTrue(collect($payload['winning_keywords'] ?? [])->contains('ফ্রড চেকার'));
        $this->assertTrue(
            collect($payload['next_post_ideas'] ?? [])->contains(
                fn ($idea) => ($idea['seed_topic'] ?? null) === 'ফ্রড চেকার'
                    || str_contains((string) ($idea['reason'] ?? ''), 'gsc_')
            )
        );

        $block = $learning->promptLearningBlock();
        $this->assertArrayHasKey('gsc_keyword_seeds', $block);
    }
}
