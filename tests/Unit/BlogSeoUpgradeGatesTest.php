<?php

namespace Tests\Unit;

use App\Models\BlogPost;
use App\Services\BlogAi\BlogContentAgent;
use App\Services\BlogSeoQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSeoUpgradeGatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_min_body_words_default_is_1200(): void
    {
        $this->assertSame(1200, (int) config('blog_ai.min_body_words'));
    }

    public function test_glossary_uses_lower_word_floor(): void
    {
        $service = app(BlogSeoQuality::class);
        $this->assertSame(800, $service->minBodyWordsForType('glossary'));
        $this->assertSame(1200, $service->minBodyWordsForType('howto'));
    }

    public function test_publish_requires_focus_keyword_by_default(): void
    {
        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'ফেক অর্ডার গাইড',
            bodyHtml: '<p><a href="/bd-fraud-checker">ফ্রড</a></p>',
            focusKeyword: null,
            metaDescription: 'Meta about fake orders for Bangladesh COD sellers with enough characters.',
            slug: 'fake-order-guide-new',
            locale: 'bn',
        );

        $this->assertArrayHasKey('focus_keyword', $errors);
    }

    public function test_soft_pass_blocks_publish_when_enabled(): void
    {
        config(['blog_ai.seo_quality.enforce_on_publish.block_soft_pass' => true]);

        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'ফেক অর্ডার গাইড',
            bodyHtml: '<p>ফেক অর্ডার <a href="/bd-fraud-checker">চেক</a></p>',
            focusKeyword: 'ফেক অর্ডার কমাতে',
            metaDescription: 'Meta about fake orders for Bangladesh COD sellers with enough characters.',
            slug: 'soft-pass-blocked',
            locale: 'bn',
            seoSoftPass: true,
        );

        $this->assertArrayHasKey('status', $errors);
    }

    public function test_soft_pass_allows_publish_when_flag_off(): void
    {
        config(['blog_ai.seo_quality.enforce_on_publish.block_soft_pass' => false]);

        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'ফেক অর্ডার গাইড',
            bodyHtml: '<p>ফেক অর্ডার <a href="/bd-fraud-checker">চেক</a></p>',
            focusKeyword: 'ফেক অর্ডার কমাতে',
            metaDescription: 'Meta about fake orders for Bangladesh COD sellers with enough characters.',
            slug: 'soft-pass-allowed',
            locale: 'bn',
            seoSoftPass: true,
        );

        $this->assertArrayNotHasKey('status', $errors);
    }

    public function test_landing_head_term_pivots_to_long_tail(): void
    {
        config(['blog_ai.lp_keyword_guard' => true]);

        /** @var BlogContentAgent $agent */
        $agent = $this->app->make(BlogContentAgent::class);

        // Force a known short head term into the blocked list via reflection-free path:
        // preferLandingSafePrimary uses landingHeadTermsForCluster — seed secondary as safe long-tail.
        $heads = $agent->landingHeadTermsForCluster('fraud_checker');
        $this->assertNotEmpty($heads);

        $head = collect($heads)->first(fn ($t) => mb_strlen((string) $t) <= 40) ?: $heads[0];

        $result = $agent->preferLandingSafePrimary([
            'primary' => $head,
            'secondary' => ['কিভাবে ফ্রড চেকার দিয়ে ফেক অর্ডার আটকাবো Bangladesh', 'COD fraud check BD longtail'],
            'suggestions' => [],
            'live_suggestions' => [],
            'cannibalization' => [],
            'auto_pivot' => null,
        ], 'fraud_checker');

        $this->assertNotSame(mb_strtolower(trim($head)), mb_strtolower(trim((string) $result['primary'])));
        $this->assertNotNull($result['auto_pivot']);
        $this->assertSame('landing_head_term', $result['auto_pivot']['reason'] ?? null);
    }

    public function test_needs_seo_fix_helper_on_model(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Draft',
            'slug' => 'draft-soft',
            'locale' => 'bn',
            'status' => 'draft',
            'body_html' => '<p>x</p>',
            'ai_quality_score' => 40,
            'seo_soft_pass' => false,
        ]);

        $this->assertTrue($post->needsSeoFix());

        $post->ai_quality_score = 90;
        $post->seo_soft_pass = true;
        $this->assertTrue($post->needsSeoFix());

        $post->seo_soft_pass = false;
        $this->assertFalse($post->needsSeoFix());
    }
}
