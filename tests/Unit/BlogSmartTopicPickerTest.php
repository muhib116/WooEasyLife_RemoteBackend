<?php

namespace Tests\Unit;

use App\Models\BlogGscQueryMetric;
use App\Models\BlogPost;
use App\Services\BlogAi\BlogSmartTopicPicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSmartTopicPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_higher_opportunity_and_skips_to_refresh_on_collision(): void
    {
        BlogPost::query()->create([
            'title' => 'Existing Fake Order Guide',
            'slug' => 'existing-fake-order',
            'locale' => 'bn',
            'status' => 'published',
            'cluster' => 'fake_order',
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p>x</p>',
            'published_at' => now(),
        ]);

        BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'ফেক অর্ডার|https://example.com/blog/existing-fake-order'),
            'query' => 'ফেক অর্ডার',
            'page_url' => 'https://example.com/blog/existing-fake-order',
            'slug' => 'existing-fake-order',
            'clicks_28d' => 5,
            'impressions_28d' => 400,
            'ctr_28d' => 0.01,
            'position_28d' => 8,
            'bucket' => BlogGscQueryMetric::BUCKET_FIX_CTR,
            'opportunity_score' => 90,
            'improvement_hint' => 'Fix CTR',
            'synced_at' => now(),
        ]);

        BlogGscQueryMetric::query()->create([
            'pair_hash' => hash('sha256', 'কুরিয়ার হিস্টোরি চেক|https://example.com/blog/other'),
            'query' => 'কুরিয়ার হিস্টোরি চেক',
            'page_url' => 'https://example.com/blog/other',
            'slug' => null,
            'clicks_28d' => 1,
            'impressions_28d' => 50,
            'ctr_28d' => 0.02,
            'position_28d' => 18,
            'bucket' => BlogGscQueryMetric::BUCKET_STRIKING,
            'opportunity_score' => 40,
            'improvement_hint' => 'Climb',
            'synced_at' => now(),
        ]);

        $pick = app(BlogSmartTopicPicker::class)->pick(null, null, [
            'next_post_ideas' => [],
        ]);

        $this->assertSame('ফেক অর্ডার', $pick['keyword']);
        $this->assertSame('refresh', $pick['action']);
        $this->assertSame('existing-fake-order', $pick['target_slug']);
    }

    public function test_explicit_seed_is_respected(): void
    {
        $pick = app(BlogSmartTopicPicker::class)->pick('fraud_checker', 'ফ্রড চেকার', []);

        $this->assertSame('ফ্রড চেকার', $pick['seed_topic']);
        $this->assertSame('fraud_checker', $pick['cluster']);
        $this->assertSame('explicit_seed', $pick['reason']);
    }
}
