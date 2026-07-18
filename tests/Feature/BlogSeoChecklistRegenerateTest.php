<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogSeoChecklistRegenerateTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-seo-regen-'.uniqid().'@example.com',
            'phone' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_regenerate_seo_checklist_improves_failing_gates(): void
    {
        config([
            'blog_ai.enabled' => true,
            'landing.openai_api_key' => 'sk-test-key',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'ফ্রড চেকার দিয়ে ফেক অর্ডার আটকান',
                            'meta_title' => 'ফ্রড চেকার — ফেক অর্ডার আটকানোর গাইড',
                            'meta_description' => 'ফ্রড চেকার দিয়ে বাংলাদেশে ফেক অর্ডার আটকান। কুরিয়ার হিস্টোরি দেখে রিটার্ন লস কমান।',
                            'excerpt' => 'ফ্রড চেকার দিয়ে বাংলাদেশে ফেক অর্ডার আটকান।',
                            'body_html' => '<p>ফ্রড চেকার দিয়ে অর্ডার কনফার্মের আগে কাস্টমার চেক করুন।</p>'
                                .'<h2>ফ্রড চেকার কীভাবে ব্যবহার করবেন</h2>'
                                .'<h3>ধাপসমূহ</h3>'
                                .'<ul><li>নম্বর দিন</li><li>রেট দেখুন</li></ul>'
                                .'<p>আরও দেখুন: <a href="/bd-fraud-checker">ফ্রড চেকার</a> · <a href="/fake-customer-check">ফেক কাস্টমার চেক</a></p>',
                            'faqs' => [
                                ['q' => 'ফ্রড চেকার কী?', 'a' => 'কুরিয়ার হিস্টোরি টুল।'],
                                ['q' => 'ফ্রি কি?', 'a' => 'দৈনিক ফ্রি চেক আছে।'],
                                ['q' => 'কোন কুরিয়ার?', 'a' => 'Pathao, Steadfast, RedX।'],
                                ['q' => 'কখন ব্যবহার করব?', 'a' => 'কনফার্মের আগে।'],
                                ['q' => 'মোবাইল লাগে?', 'a' => 'হ্যাঁ, নম্বর দিতে হয়।'],
                            ],
                            'quick_answer' => 'ফ্রড চেকার দিয়ে নম্বর চেক করে ফেক অর্ডার আটকান।',
                            'ai_search_summary' => 'WooEasyLife ফ্রড চেকার BD সেলারদের কুরিয়ার হিস্টোরি দেখায় যাতে ফেক অর্ডার কমে।',
                            'notes' => ['Added keyword placements and FAQ'],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 200, 'total_tokens' => 300],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)
            ->postJson(route('blogAi.regenerateSeoChecklist'), [
                'title' => 'অর্ডার গাইড',
                'focus_keyword' => 'ফ্রড চেকার',
                'body_html' => '<p>সাধারণ লেখা।</p>',
                'meta_description' => 'short',
                'faqs_json' => [],
                'locale' => 'bn',
            ]);

        $response->assertOk()
            ->assertJsonPath('focus_keyword', 'ফ্রড চেকার');

        $body = (string) $response->json('body_html');
        $this->assertStringContainsString('ফ্রড চেকার', (string) $response->json('title'));
        $this->assertStringContainsString('seo-quick-answer', $body);
        $this->assertStringContainsString('seo-ai-summary', $body);
        $this->assertGreaterThanOrEqual(5, count($response->json('faqs_json')));
        $this->assertIsInt($response->json('ai_quality_score'));
    }

    public function test_regenerate_skips_openai_when_only_cover_image_missing(): void
    {
        config([
            'blog_ai.enabled' => true,
            'landing.openai_api_key' => 'sk-test-key',
            'blog_ai.min_body_words' => 50,
        ]);

        Http::fake();

        $admin = $this->adminUser();

        $body = '<section class="seo-quick-answer"><h2>দ্রুত উত্তর</h2><p>ফ্রড চেকার দিয়ে অর্ডার কনফার্মের আগে কাস্টমার চেক করুন।</p></section>'
            .'<section class="seo-ai-summary"><h2>এআই সারাংশ</h2><p>ফ্রড চেকার BD সেলারদের রিটার্ন লস কমাতে সাহায্য করে এবং ফেক অর্ডার আটকায়।</p></section>'
            .'<p>ফ্রড চেকার দিয়ে অর্ডার কনফার্মের আগে কাস্টমার চেক করুন এবং রিটার্ন লস কমান। একাধিক নম্বর যাচাই করে রেটিং দেখুন।</p>'
            .'<h2>ফ্রড চেকার ব্যবহার</h2><h3>ধাপ</h3><ul><li>মোবাইল নম্বর দিন</li><li>রেট ও হিস্ট্রি দেখুন</li><li>ঝুঁকি বুঝে কনফার্ম করুন</li></ul>'
            .'<p>বাংলাদেশ কুরিয়ার সেলাররা প্রতিদিন ফেক অর্ডার ও COD রিটার্নে লস করেন। সঠিক চেকিং ওয়ার্কফ্লো ব্যবসা বাঁচায়।</p>'
            .'<p><a href="/bd-fraud-checker">ফ্রড চেকার</a> · <a href="/fake-customer-check">চেক</a></p>';

        $faqs = [];
        for ($i = 1; $i <= 5; $i++) {
            $faqs[] = ['q' => "প্রশ্ন {$i} ফ্রড চেকার?", 'a' => "উত্তর {$i}"];
        }

        $response = $this->actingAs($admin)
            ->postJson(route('blogAi.regenerateSeoChecklist'), [
                'title' => 'ফ্রড চেকার গাইড',
                'focus_keyword' => 'ফ্রড চেকার',
                'body_html' => $body,
                'meta_description' => 'ফ্রড চেকার দিয়ে বাংলাদেশে ফেক অর্ডার আটকান এবং রিটার্ন লস কমান সহজে।',
                'faqs_json' => $faqs,
                'locale' => 'bn',
                'og_image' => null,
            ])
            ->assertOk();

        Http::assertNothingSent();
        $notes = implode(' ', $response->json('notes') ?? []);
        expect($notes)->toContain('OG/cover');
    }
}
