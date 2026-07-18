<?php

namespace Tests\Unit;

use App\Models\BlogPost;
use App\Services\BlogSeoQuality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSeoQualityTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_detects_core_signals(): void
    {
        $service = app(BlogSeoQuality::class);

        $filler = str_repeat('ফেক অর্ডার কমাতে নিয়মিত কুরিয়ার হিস্টোরি ও কনফার্মেশন কল চালু রাখুন। ', 80);
        $body = <<<HTML
<section class="seo-quick-answer"><h2>দ্রুত উত্তর</h2><p>ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি চেক করুন।</p></section>
<p>বাংলাদেশে ফেক অর্ডার COD ব্যবসার বড় লস।</p>
<h2>ফেক অর্ডার কমানোর ধাপ</h2>
<h3>কুরিয়ার হিস্টোরি</h3>
<ul><li>নম্বর চেক</li></ul>
<ol><li>কনফার্ম কল</li></ol>
<p>কাজের ধাপ এবং <a href="/bd-fraud-checker">ফ্রড চেকার</a> ও <a href="/">WooEasyLife</a>।</p>
<p>{$filler}</p>
<section class="seo-ai-summary"><h2>এআই সারাংশ</h2><p>ফেক অর্ডার আটকাতে হিস্টোরি, OTP ও কুরিয়ার অটো এন্ট্রি একসাথে ব্যবহার করুন।</p></section>
<figure><img src="/storage/cover.webp" alt="ফেক অর্ডার"></figure>
HTML;

        $quality = $service->analyze(
            title: 'ফেক অর্ডার কমানোর উপায়',
            focusKeyword: 'ফেক অর্ডার',
            bodyHtml: $body,
            metaDescription: 'বাংলাদেশের COD সেলারদের জন্য ফেক অর্ডার কমানোর ব্যবহারিক গাইড এবং টিপস।',
            faqs: [
                ['q' => 'ফেক অর্ডার কী?', 'a' => 'নেয় না এমন অর্ডার।'],
                ['q' => 'কীভাবে চেক করব?', 'a' => 'কুরিয়ার হিস্টোরি দেখুন।'],
                ['q' => 'কোন টুল?', 'a' => 'WooEasyLife fraud checker।'],
                ['q' => 'OTP লাগে?', 'a' => 'চেকআউট OTP ফেক অর্ডার কমায়।'],
                ['q' => 'কুরিয়ার অটো?', 'a' => 'কনফার্ম হলেই এন্ট্রি হয়।'],
            ],
            secondaryKeywords: ['কুরিয়ার হিস্টোরি'],
            slug: 'fake-order-guide',
        );

        $this->assertTrue($quality['has_h2']);
        $this->assertTrue($quality['has_h3']);
        $this->assertTrue($quality['has_lists']);
        $this->assertTrue($quality['internal_links_ok']);
        $this->assertTrue($quality['keyword_in_title']);
        $this->assertTrue($quality['keyword_in_first_paragraph']);
        $this->assertTrue($quality['keyword_in_h2']);
        $this->assertTrue($quality['keyword_in_meta']);
        $this->assertTrue($quality['faq_count_ok']);
        $this->assertTrue($quality['has_quick_answer']);
        $this->assertTrue($quality['has_ai_search_summary']);
        $this->assertTrue($quality['has_content_image']);
        $this->assertTrue($quality['content_image_alt_ok']);
        $this->assertTrue($quality['secondary_keyword_in_body']);
        $this->assertFalse($quality['slug_collision']);
        $this->assertTrue($quality['ai_ready']);
    }

    public function test_ensure_seo_content_blocks_injects_missing_sections(): void
    {
        $service = app(BlogSeoQuality::class);
        $body = '<p>বাংলাদেশে ফেক অর্ডার সমস্যা।</p><h2>ফেক অর্ডার ধাপ</h2><p>বিস্তারিত।</p>';
        $out = $service->ensureSeoContentBlocks(
            $body,
            'ফেক অর্ডার কমাতে প্রথমে হিস্টোরি চেক করুন।',
            'এই গাইডে ফেক অর্ডার আটকানোর ব্যবহারিক ধাপ আছে।',
        );

        $this->assertTrue($service->hasQuickAnswer($out));
        $this->assertTrue($service->hasAiSearchSummary($out));
        $this->assertStringContainsString('seo-quick-answer', $out);
        $this->assertStringContainsString('seo-ai-summary', $out);
    }

    public function test_ensure_internal_links_appends_missing(): void
    {
        $service = app(BlogSeoQuality::class);
        $body = '<p>Hello world</p>';
        $fixed = $service->ensureInternalLinks($body, [
            ['path' => '/bd-fraud-checker', 'anchor' => 'Fraud'],
            ['path' => '/', 'anchor' => 'Home'],
        ], 2);

        $this->assertSame(2, preg_match_all('/href=["\']\//', $fixed));
        $this->assertStringContainsString('/bd-fraud-checker', $fixed);
    }

    public function test_inject_content_image_once(): void
    {
        $service = app(BlogSeoQuality::class);
        $body = '<p>First</p><p>Second</p>';
        $once = $service->injectContentImage($body, 'https://example.com/a.webp', 'alt text', 'Caption');
        $twice = $service->injectContentImage($once, 'https://example.com/a.webp', 'alt text');

        $this->assertSame(1, preg_match_all('/<img\b/i', $once));
        $this->assertSame(1, preg_match_all('/<img\b/i', $twice));
        $this->assertStringContainsString('alt="alt text"', $once);
        $this->assertMatchesRegularExpression('/<\/p>\s*<figure>/i', $once);
    }

    public function test_inject_content_image_prepends_when_body_starts_with_heading(): void
    {
        $service = app(BlogSeoQuality::class);
        $body = '<h2>Intro</h2><p>ফেক অর্ডার কমানোর উপায়।</p>';
        $out = $service->injectContentImage($body, 'https://example.com/b.webp', 'ফেক অর্ডার');

        $this->assertMatchesRegularExpression('/^\s*<figure>/i', $out);
        $this->assertStringContainsString('<h2>Intro</h2>', $out);
    }

    public function test_latin_keyword_avoids_substring_false_positive(): void
    {
        $service = app(BlogSeoQuality::class);
        $this->assertFalse($service->textContainsKeyword('courier checker tools', 'check'));
        $this->assertTrue($service->textContainsKeyword('how to check fraud', 'check'));
        $this->assertTrue($service->textContainsKeyword('ফেক অর্ডার কমান', 'ফেক অর্ডার'));
    }

    public function test_soft_warnings_for_publish(): void
    {
        $warnings = app(BlogSeoQuality::class)->softWarningsForPublish(
            title: 'Generic seller tips',
            bodyHtml: '<p>No image here</p><p><a href="/">x</a></p>',
            focusKeyword: 'ফেক অর্ডার',
            ogImage: null,
        );

        $this->assertNotEmpty($warnings);
        $this->assertTrue(collect($warnings)->contains(fn ($w) => str_contains($w, 'title')));
        $this->assertTrue(collect($warnings)->contains(fn ($w) => str_contains($w, 'OG')));
    }

    public function test_focus_keyword_collision_blocks_publish_validation(): void
    {
        BlogPost::create([
            'title' => 'Existing',
            'slug' => 'existing-post',
            'locale' => 'bn',
            'status' => 'published',
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p><a href="/">x</a></p>',
            'published_at' => now(),
        ]);

        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'New guide',
            bodyHtml: '<p>New <a href="/bd-fraud-checker">link</a></p>',
            focusKeyword: 'ফেক অর্ডার',
            metaDescription: 'Meta about the topic for sellers in Bangladesh with enough length.',
            slug: 'new-post',
            locale: 'bn',
        );

        $this->assertArrayHasKey('focus_keyword', $errors);
    }

    public function test_publish_requires_internal_link(): void
    {
        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'No Link Post about fraud',
            bodyHtml: '<h2>Hello fraud</h2><p>No internal URL here about fraud topics for sellers.</p>',
            focusKeyword: 'fraud',
            metaDescription: 'Publishing without an internal link should fail validation for SEO.',
            slug: 'no-link-post',
            locale: 'en',
            faqs: [
                ['q' => 'Q1?', 'a' => 'A1'],
                ['q' => 'Q2?', 'a' => 'A2'],
                ['q' => 'Q3?', 'a' => 'A3'],
                ['q' => 'Q4?', 'a' => 'A4'],
                ['q' => 'Q5?', 'a' => 'A5'],
            ],
            ogImage: '/images/seo/og-default.jpg',
        );

        $this->assertArrayHasKey('body_html', $errors);
    }

    public function test_publish_requires_full_ai_ready_checklist(): void
    {
        $errors = app(BlogSeoQuality::class)->publishValidationErrors(
            title: 'Short post',
            bodyHtml: '<p><a href="/bd-fraud-checker">x</a></p>',
            focusKeyword: 'ফেক অর্ডার',
            metaDescription: 'short',
            slug: 'short-post',
            locale: 'bn',
            ogImage: null,
        );

        $this->assertNotEmpty($errors);
    }
}
