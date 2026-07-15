<?php

namespace Tests\Unit;

use App\Models\BlogPost;
use App\Services\BlogAi\BlogContentAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class BlogPreferNonCollidingPrimaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_pivots_primary_away_from_existing_focus_keyword(): void
    {
        BlogPost::query()->create([
            'title' => 'ফেক অর্ডার গাইড',
            'slug' => 'fake-order-guide',
            'excerpt' => 'x',
            'body_html' => '<p>x</p>',
            'status' => 'published',
            'locale' => 'bn',
            'focus_keyword' => 'ফেক অর্ডার',
        ]);

        /** @var BlogContentAgent $agent */
        $agent = $this->app->make(BlogContentAgent::class);

        $result = $agent->preferNonCollidingPrimary([
            'primary' => 'ফেক অর্ডার',
            'secondary' => ['কুরিয়ার হিস্টোরি চেক', 'COD fraud Bangladesh'],
            'suggestions' => [],
            'live_suggestions' => [],
            'cannibalization' => [
                [
                    'id' => 1,
                    'title' => 'ফেক অর্ডার গাইড',
                    'focus_keyword' => 'ফেক অর্ডার',
                    'status' => 'published',
                ],
            ],
            'auto_pivot' => null,
            'usage' => [],
        ]);

        $this->assertSame('কুরিয়ার হিস্টোরি চেক', $result['primary']);
        $this->assertSame('ফেক অর্ডার', $result['auto_pivot']['from']);
        $this->assertSame('কুরিয়ার হিস্টোরি চেক', $result['auto_pivot']['to']);
    }

    public function test_normalize_draft_auto_pivots_published_focus_keyword(): void
    {
        config(['blog_ai.min_body_words' => 5, 'blog_ai.seo_quality.min_faqs' => 1]);

        BlogPost::query()->create([
            'title' => 'Existing published',
            'slug' => 'existing-published',
            'excerpt' => 'x',
            'body_html' => '<p><a href="/">x</a></p>',
            'status' => 'published',
            'locale' => 'bn',
            'focus_keyword' => 'ফেক অর্ডার',
            'published_at' => now(),
        ]);

        /** @var BlogContentAgent $agent */
        $agent = $this->app->make(BlogContentAgent::class);
        $method = new ReflectionMethod(BlogContentAgent::class, 'normalizeDraft');
        $method->setAccessible(true);

        $body = '<h2>এক</h2><p>ফেক অর্ডার WooEasyLife BD নিয়ে আলোচনা। <a href="/bd-fraud-checker">ফ্রড চেকার</a> এবং <a href="/pricing">প্রাইসিং</a>।</p>';

        $draft = $method->invoke(
            $agent,
            [
                'title' => 'ফেক অর্ডার WooEasyLife BD গাইড',
                'slug' => 'new-unique-slug-abc',
                'focus_keyword' => 'ফেক অর্ডার',
                'meta_title' => 'ফেক অর্ডার WooEasyLife BD গাইড',
                'meta_description' => 'ফেক অর্ডার WooEasyLife BD নিয়ে বাংলাদেশের সেলারদের জন্য বাস্তব গাইড এবং টিপস।',
                'excerpt' => 'সারাংশ',
                'body_html' => $body,
                'faqs' => [['q' => 'কেন?', 'a' => 'কারণ COD ফ্রড বাড়ে।']],
            ],
            'Muhibbullah Ansary',
            [],
            [
                ['path' => '/bd-fraud-checker', 'anchor' => 'ফ্রড চেকার'],
                ['path' => '/pricing', 'anchor' => 'প্রাইসিং'],
            ],
            ['secondary' => ['কুরিয়ার হিস্টোরি চেক']],
            'fake_order',
        );

        $this->assertNotSame('ফেক অর্ডার', $draft['focus_keyword']);
        $this->assertNotNull($draft['auto_keyword_pivot']);
        $this->assertFalse($draft['quality']['focus_keyword_collision']);
    }
}
