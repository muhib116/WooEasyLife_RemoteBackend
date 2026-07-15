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
}
