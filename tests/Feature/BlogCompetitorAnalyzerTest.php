<?php

namespace Tests\Feature;

use App\Models\BlogCompetitorAnalysis;
use App\Models\BlogLearningInsight;
use App\Models\User;
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
                .'<h2>Check history</h2><p>'.str_repeat('Seller tip. ', 80).'</p></body></html>',
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
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('item.keyword', 'ফেক অর্ডার');

        $this->assertDatabaseHas('blog_competitor_analyses', [
            'keyword' => 'ফেক অর্ডার',
            'cluster' => 'fake_order',
        ]);

        $this->assertGreaterThan(0, $response->json('intelligence.score'));
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
            ->assertJsonStructure(['enabled', 'items', 'intelligence']);
    }
}
