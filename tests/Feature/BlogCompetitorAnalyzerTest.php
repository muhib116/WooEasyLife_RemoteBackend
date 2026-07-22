<?php

namespace Tests\Feature;

use App\Models\BlogCompetitorAnalysis;
use App\Models\BlogLearningInsight;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogAi\BlogCompetitorAnalyzer;
use App\Services\BlogAi\BlogCompetitorDiscoveryService;
use App\Services\BlogAi\BlogCompetitorGapService;
use App\Services\BlogAi\BlogIntelligenceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogCompetitorAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000088',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'landing.openai_api_key' => 'sk-test-key',
            'landing.openai_blog_model' => 'gpt-4o-mini',
            'blog_ai.enabled' => true,
            'blog_ai.competitors.enabled' => true,
            'blog_ai.competitors.in_prompts' => true,
            'blog_ai.competitors.discovery.enabled' => true,
            'blog_ai.competitors.discovery.provider' => 'duckduckgo',
            'blog_ai.competitors.discovery.auto_on_smart_post' => true,
        ]);
    }

    public function test_intelligence_score_includes_dimensions(): void
    {
        $score = app(BlogIntelligenceScorer::class)->score();

        $this->assertArrayHasKey('score', $score);
        $this->assertArrayHasKey('label', $score);
        $this->assertArrayHasKey('dimensions', $score);
        $this->assertGreaterThanOrEqual(0, $score['score']);
        $this->assertLessThanOrEqual(100, $score['score']);

        $keys = collect($score['dimensions'])->pluck('key')->all();
        $this->assertContains('ai_writer', $keys);
        $this->assertContains('gsc', $keys);
        $this->assertContains('competitors', $keys);
        $this->assertContains('learning', $keys);
        $this->assertContains('analytics', $keys);
    }

    public function test_intelligence_rises_with_learning_and_competitor(): void
    {
        $before = app(BlogIntelligenceScorer::class)->score()['score'];

        BlogLearningInsight::query()->create([
            'scope' => 'global',
            'generated_at' => now(),
            'summary_bn' => 'টেস্ট',
            'posts_analyzed' => 3,
            'events_analyzed' => 10,
            'payload_json' => ['recommended_clusters' => ['fake_order']],
        ]);

        BlogCompetitorAnalysis::query()->create([
            'keyword' => 'ফেক অর্ডার',
            'cluster' => 'fake_order',
            'competitor_urls' => ['https://example.com/a'],
            'insight_json' => ['content_gaps' => ['BD COD checklist']],
            'summary_bn' => 'প্রতিযোগী দুর্বল',
            'beat_score' => 72,
        ]);

        $after = app(BlogIntelligenceScorer::class)->score()['score'];
        $this->assertGreaterThan($before, $after);
    }

    public function test_admin_can_analyze_competitors(): void
    {
        Http::fake([
            'https://example.com/competitor-post' => Http::response(
                '<html><head><title>Fake Order Tips</title>'
                .'<meta name="description" content="How to reduce fake COD">'
                .'</head><body><h1>Fake Order Tips</h1>'
                .'<h2>Check history</h2><h3>Courier tips</h3><ul><li>One</li><li>Two</li></ul>'
                .'<p>'.str_repeat('Seller tip. ', 80).'</p></body></html>',
                200
            ),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary_bn' => 'প্রতিযোগী সাধারণ টিপস দিয়েছে; আমরা টুল লিংক দিয়ে জিতব।',
                            'beat_score' => 78,
                            'competitor_strengths' => ['Clear H1'],
                            'competitor_weaknesses' => ['No BD courier tools'],
                            'content_gaps' => ['Return loss calculator angle'],
                            'must_cover_angles' => ['Practical COD checklist'],
                            'title_angles' => ['ফেক অর্ডার কমাতে চেকলিস্ট'],
                            'faq_gaps' => ['Pathao steadfast কীভাবে চেক করব?'],
                            'differentiation' => ['Link /bd-fraud-checker'],
                            'writing_guidance' => ['Lead with BD seller pain'],
                            'gap_checklist' => [
                                [
                                    'id' => 'g1',
                                    'gap' => 'Practical COD checklist',
                                    'why' => 'Competitors skip steps',
                                    'status' => 'open',
                                    'evidence' => null,
                                ],
                                [
                                    'id' => 'g2',
                                    'gap' => 'Return loss calculator',
                                    'why' => 'Local angle',
                                    'status' => 'open',
                                    'evidence' => null,
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150,
                ],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('blogAi.competitors.analyze'), [
            'keyword' => 'ফেক অর্ডার',
            'cluster' => 'fake_order',
            'urls_text' => "https://example.com/competitor-post\n",
            'allow_discover' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('item.keyword', 'ফেক অর্ডার')
            ->assertJsonPath('item.open_gaps', 2);

        $this->assertDatabaseHas('blog_competitor_analyses', [
            'keyword' => 'ফেক অর্ডার',
            'cluster' => 'fake_order',
        ]);

        $block = app(BlogCompetitorAnalyzer::class)->promptBlockForKeyword('ফেক অর্ডার');
        $this->assertNotNull($block);
        $this->assertContains('Practical COD checklist', $block['diff_checklist']);
        $this->assertNotEmpty($block['gap_checklist']);

        $this->assertGreaterThan(0, $response->json('intelligence.score'));
    }

    public function test_discover_competitors_route(): void
    {
        Http::fake([
            'html.duckduckgo.com/*' => Http::response(
                '<html><body>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://rival.example/cod-guide').'">COD Guide</a>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://other.example/fake-orders').'">Fake Orders</a>'
                .'</body></html>',
                200
            ),
        ]);

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('blogAi.competitors.discover'), [
            'keyword' => 'ফেক অর্ডার',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(2, 'results');

        $this->assertStringContainsString('rival.example', (string) $response->json('urls_text'));
    }

    public function test_analyze_auto_discovers_when_urls_empty(): void
    {
        Http::fake([
            'html.duckduckgo.com/*' => Http::response(
                '<html><body>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://example.com/competitor-post').'">Rival</a>'
                .'</body></html>',
                200
            ),
            'https://example.com/competitor-post' => Http::response(
                '<html><head><title>Rival</title></head><body><h1>Rival</h1><h2>Tips</h2>'
                .'<p>'.str_repeat('Word ', 100).'</p></body></html>',
                200
            ),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary_bn' => 'আবিষ্কৃত প্রতিযোগী বিশ্লেষণ।',
                            'beat_score' => 70,
                            'competitor_strengths' => ['OK'],
                            'competitor_weaknesses' => ['Thin'],
                            'content_gaps' => ['BD tools'],
                            'must_cover_angles' => ['Checklist'],
                            'title_angles' => ['Title'],
                            'faq_gaps' => ['FAQ'],
                            'differentiation' => ['Tool'],
                            'writing_guidance' => ['Be practical'],
                            'gap_checklist' => [
                                ['id' => 'g1', 'gap' => 'Checklist', 'status' => 'open'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('blogAi.competitors.analyze'), [
            'keyword' => 'ফেক অর্ডার কমাতে',
            'cluster' => 'fake_order',
            'urls_text' => '',
            'allow_discover' => true,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertTrue((bool) $response->json('item.discovered'));
        $this->assertDatabaseHas('blog_competitor_analyses', [
            'keyword' => 'ফেক অর্ডার কমাতে',
        ]);
    }

    public function test_ensure_analysis_for_keyword_auto_discovers(): void
    {
        Http::fake([
            'html.duckduckgo.com/*' => Http::response(
                '<html><body>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://example.com/auto').'">Auto</a>'
                .'</body></html>',
                200
            ),
            'https://example.com/auto' => Http::response(
                '<html><body><h1>Auto</h1><h2>A</h2><p>'.str_repeat('x ', 80).'</p></body></html>',
                200
            ),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary_bn' => 'Smart post auto.',
                            'beat_score' => 66,
                            'competitor_strengths' => [],
                            'competitor_weaknesses' => [],
                            'content_gaps' => ['Gap'],
                            'must_cover_angles' => ['Angle'],
                            'title_angles' => [],
                            'faq_gaps' => [],
                            'differentiation' => [],
                            'writing_guidance' => [],
                            'gap_checklist' => [
                                ['id' => 'g1', 'gap' => 'Angle', 'status' => 'open'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
            ], 200),
        ]);

        $result = app(BlogCompetitorAnalyzer::class)->ensureAnalysisForKeyword('স্মার্ট কীওয়ার্ড', 'fake_order');
        $this->assertNotNull($result);
        $this->assertArrayHasKey('prompt_block', $result);
        $this->assertContains('Angle', $result['prompt_block']['diff_checklist']);

        // Second call should no-op because fresh analysis exists.
        $again = app(BlogCompetitorAnalyzer::class)->ensureAnalysisForKeyword('স্মার্ট কীওয়ার্ড', 'fake_order');
        $this->assertNull($again);
    }

    public function test_gap_coverage_and_our_snapshot(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'ফেক অর্ডার কমাতে চেকলিস্ট',
            'slug' => 'fake-order-checklist-test',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'article_type' => 'howto',
            'status' => 'published',
            'excerpt' => 'Test',
            'meta_title' => 'ফেক অর্ডার',
            'meta_description' => str_repeat('meta ', 20),
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<h2>Practical COD checklist</h2><p>'.str_repeat('Seller tip. ', 50).'</p>',
            'faqs_json' => [['q' => 'কীভাবে চেক করব?', 'a' => 'History দেখুন।']],
            'published_at' => now(),
            'author_name' => 'Tester',
        ]);

        $gaps = app(BlogCompetitorGapService::class);
        $found = $gaps->findOurPost('ফেক অর্ডার', 'fake_order');
        $this->assertNotNull($found);
        $this->assertSame($post->id, $found->id);

        $snapshot = $gaps->ourSnapshot($found);
        $this->assertNotEmpty($snapshot['headings']);

        $coverage = $gaps->measureCoverage(
            ['Practical COD checklist', 'Missing angle nowhere'],
            (string) $post->body_html,
            $post->faqs_json ?? [],
            $post->title
        );
        $this->assertSame(2, $coverage['total']);
        $this->assertSame(1, $coverage['covered']);
        $this->assertSame(50, $coverage['pct']);
    }

    public function test_discovery_service_excludes_own_hosts(): void
    {
        config([
            'app.url' => 'https://app.wpsalehub.com',
            'blog_ai.competitors.discovery.exclude_hosts' => ['wpsalehub.com', 'app.wpsalehub.com'],
        ]);

        Http::fake([
            'html.duckduckgo.com/*' => Http::response(
                '<html><body>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://app.wpsalehub.com/blog/x').'">Own</a>'
                .'<a class="result__a" href="//duckduckgo.com/l/?uddg='.rawurlencode('https://rival.test/post').'">Rival</a>'
                .'</body></html>',
                200
            ),
        ]);

        $results = app(BlogCompetitorDiscoveryService::class)->discover('ফেক অর্ডার');
        $this->assertCount(1, $results);
        $this->assertSame('https://rival.test/post', $results[0]['url']);
    }

    public function test_discovery_falls_back_to_duckduckgo_lite(): void
    {
        config([
            'blog_ai.competitors.discovery.provider' => 'auto',
            'blog_ai.competitors.discovery.api_key' => '',
            'blog_ai.competitors.discovery.bing_api_key' => '',
        ]);

        Http::fake([
            'html.duckduckgo.com/*' => Http::response('no results', 200),
            'lite.duckduckgo.com/*' => Http::response(
                '<html><body><a rel="nofollow" href="https://rival.example/cod-guide">COD Guide</a></body></html>',
                200
            ),
        ]);

        $results = app(\App\Services\BlogAi\BlogCompetitorDiscoveryService::class)->discover('ফেক অর্ডার');
        $this->assertNotEmpty($results);
        $this->assertSame('duckduckgo_lite', $results[0]['provider']);
        $this->assertStringContainsString('rival.example', $results[0]['url']);
    }

    public function test_blocks_private_and_localhost_urls(): void
    {
        $analyzer = app(BlogCompetitorAnalyzer::class);

        $this->assertFalse($analyzer->isSafePublicHttpUrl('http://127.0.0.1/admin'));
        $this->assertFalse($analyzer->isSafePublicHttpUrl('http://localhost/secret'));
        $this->assertFalse($analyzer->isSafePublicHttpUrl('http://10.0.0.5/internal'));
        $this->assertFalse($analyzer->isSafePublicHttpUrl('http://169.254.169.254/latest/meta-data'));
        $this->assertTrue($analyzer->isSafePublicHttpUrl('https://example.com/competitor-post'));

        $admin = $this->adminUser();
        $response = $this->actingAs($admin)->postJson(route('blogAi.competitors.analyze'), [
            'keyword' => 'ফেক অর্ডার',
            'urls_text' => "http://127.0.0.1/\nhttp://localhost/x",
            'allow_discover' => false,
        ]);

        $response->assertStatus(422);
    }

    public function test_find_our_post_does_not_use_unrelated_cluster_post(): void
    {
        BlogPost::query()->create([
            'title' => 'Unrelated courier post',
            'slug' => 'unrelated-courier-post',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'article_type' => 'howto',
            'status' => 'published',
            'excerpt' => 'Test',
            'meta_title' => 'Courier',
            'meta_description' => str_repeat('meta ', 20),
            'focus_keyword' => 'কুরিয়ার চার্জ',
            'body_html' => '<h2>Courier</h2><p>'.str_repeat('tip ', 40).'</p>',
            'faqs_json' => [],
            'published_at' => now(),
            'author_name' => 'Tester',
        ]);

        $gaps = app(BlogCompetitorGapService::class);
        $this->assertNull($gaps->findOurPost('ফেক অর্ডার', 'fake_order'));
    }

    public function test_safe_redirect_follow_rechecks_target(): void
    {
        Http::fake([
            'https://example.com/start' => Http::response('', 301, [
                'Location' => 'https://example.com/final',
            ]),
            'https://example.com/final' => Http::response(
                '<html><head><title>Final</title></head><body><h1>Final</h1><h2>Gap</h2>'
                .'<p>'.str_repeat('Word ', 80).'</p></body></html>',
                200
            ),
            'https://example.com/evil' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/secret',
            ]),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'summary_bn' => 'Redirect ok.',
                            'beat_score' => 70,
                            'competitor_strengths' => [],
                            'competitor_weaknesses' => [],
                            'content_gaps' => ['Gap'],
                            'must_cover_angles' => ['Angle'],
                            'title_angles' => [],
                            'faq_gaps' => [],
                            'differentiation' => [],
                            'writing_guidance' => [],
                            'gap_checklist' => [
                                ['id' => 'g1', 'gap' => 'Angle', 'status' => 'open'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10],
            ], 200),
        ]);

        $admin = $this->adminUser();

        $ok = $this->actingAs($admin)->postJson(route('blogAi.competitors.analyze'), [
            'keyword' => 'redirect test',
            'urls_text' => 'https://example.com/start',
            'allow_discover' => false,
        ]);
        $ok->assertOk()->assertJsonPath('ok', true);

        $evil = $this->actingAs($admin)->postJson(route('blogAi.competitors.analyze'), [
            'keyword' => 'evil redirect',
            'urls_text' => 'https://example.com/evil',
            'allow_discover' => false,
        ]);
        $evil->assertStatus(422);
    }

    public function test_competitors_index_and_intelligence_routes(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson(route('blogAi.intelligence'))
            ->assertOk()
            ->assertJsonStructure(['score', 'label', 'dimensions']);

        $this->actingAs($admin)
            ->getJson(route('blogAi.competitors.index'))
            ->assertOk()
            ->assertJsonStructure(['enabled', 'discovery_enabled', 'items', 'intelligence']);
    }
}
