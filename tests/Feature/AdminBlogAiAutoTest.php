<?php

namespace Tests\Feature;

use App\Models\BlogAiRun;
use App\Models\BlogAiSession;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBlogAiAutoTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-auto-'.uniqid().'@example.com',
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
            'blog_ai.queue' => false,
            'blog_ai.image_enabled' => false,
            'blog_ai.require_pasted_keywords' => true,
            'blog_ai.min_body_words' => 50,
            'blog_ai.auto.enabled' => true,
            'blog_ai.auto.create_post' => true,
            'blog_ai.auto.use_llm_review' => false,
            'blog_ai.auto.max_revisions_per_step' => 1,
            'blog_ai.seo_quality.min_faqs' => 1,
            'blog_ai.seo_quality.min_internal_links' => 2,
            // Keep auto fixtures focused; production defaults enable these gates.
            'blog_ai.seo_quality.require_keyword_in_h2' => false,
            'blog_ai.seo_quality.require_quick_answer' => false,
            'blog_ai.seo_quality.require_ai_search_summary' => false,
            'blog_ai.seo_quality.require_h3' => false,
            'blog_ai.seo_quality.require_lists' => false,
        ]);
    }

    private function fakeAutoHttp(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'suggestqueries.google.com')) {
                return Http::response(['ফেক অর্ডার', ['ফেক অর্ডার চেক', 'ফেক অর্ডার কমানোর উপায়']], 200);
            }

            $system = (string) data_get($request->data(), 'messages.0.content', '');

            if (str_contains($system, 'keyword candidates')) {
                $payload = ['keywords' => ['ফেক অর্ডার', 'কুরিয়ার হিস্টোরি চেক', 'COD fraud Bangladesh']];
            } elseif (str_contains($system, 'keyword planning')) {
                $payload = [
                    'primary' => 'ফেক অর্ডার',
                    'secondary' => ['কুরিয়ার হিস্টোরি চেক', 'COD fraud Bangladesh'],
                    'suggestions' => [],
                ];
            } elseif (str_contains($system, 'hook titles')) {
                $payload = [
                    'hooks' => [
                        [
                            'id' => 'h1',
                            'title' => 'ফেক অর্ডার কমানোর উপায়',
                            'focus_keyword' => 'ফেক অর্ডার',
                            'angle' => 'howto',
                            'why_it_ranks' => 'BD intent',
                            'risk' => '',
                        ],
                        [
                            'id' => 'h2',
                            'title' => 'কুরিয়ার হিস্টোরি চেকলিস্ট',
                            'focus_keyword' => 'কুরিয়ার হিস্টোরি',
                            'angle' => 'checklist',
                            'why_it_ranks' => 'Practical',
                            'risk' => '',
                        ],
                        [
                            'id' => 'h3',
                            'title' => 'ফেক অর্ডার vs সত্যিকারের অর্ডার',
                            'focus_keyword' => 'ফেক অর্ডার',
                            'angle' => 'comparison',
                            'why_it_ranks' => 'Differentiation',
                            'risk' => '',
                        ],
                    ],
                ];
            } elseif (str_contains($system, 'SEO outline') || str_contains($system, 'Content Planner')) {
                $payload = [
                    'h1' => 'ফেক অর্ডার কমানোর উপায়',
                    'focus_keyword' => 'ফেক অর্ডার',
                    'slug_suggestion' => 'fake-order-auto-guide',
                    'sections' => [
                        ['heading' => 'কেন ফেক অর্ডার হয়', 'h3' => ['কারণ'], 'bullets' => ['COD', 'ঠিকানা ভুল']],
                        ['heading' => 'কুরিয়ার হিস্টোরি কীভাবে দেখবেন', 'bullets' => ['ফোন', 'ট্র্যাকিং']],
                        ['heading' => 'চেকলিস্ট', 'bullets' => ['OTP', 'ব্লক']],
                        ['heading' => 'WooEasyLife দিয়ে কীভাবে', 'bullets' => ['fraud checker']],
                    ],
                    'faqs' => [
                        ['q' => 'ফেক অর্ডার কী?', 'a_points' => ['নেয় না এমন অর্ডার']],
                        ['q' => 'হিস্টোরি কেন লাগে?', 'a_points' => ['ঝুঁকি দেখে']],
                        ['q' => 'শুরু কিভাবে?', 'a_points' => ['WooEasyLife']],
                        ['q' => 'OTP লাগে?', 'a_points' => ['চেকআউট OTP']],
                        ['q' => 'অটো এন্ট্রি?', 'a_points' => ['কুরিয়ার অটো']],
                    ],
                    'internal_links' => [
                        ['path' => '/fake-order-protection', 'anchor' => 'ফেক অর্ডার প্রোটেকশন', 'reason' => 'landing'],
                        ['path' => '/bd-fraud-checker', 'anchor' => 'ফ্রড চেকার', 'reason' => 'tool'],
                    ],
                    'cta' => 'WooEasyLife দিয়ে শুরু করুন',
                ];
            } else {
                $body = '<section class="seo-quick-answer"><h2>দ্রুত উত্তর</h2><p>ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি চেক করুন।</p></section>'
                    .'<p>ফেক অর্ডার বাংলাদেশের COD সেলারদের বড় সমস্যা। কুরিয়ার হিস্টোরি চেক এবং COD fraud Bangladesh প্যাটার্ন বুঝলে লোকসান কমে। '
                    .'<a href="/fake-order-protection">ফেক অর্ডার প্রোটেকশন</a> ও <a href="/bd-fraud-checker">ফ্রড চেকার</a> দিয়ে অপারেশন সহজ হয়। '
                    .str_repeat('বিস্তারিত ধাপ ও উদাহরণ সহ ব্যবহারিক গাইড। ', 40)
                    .'</p>'
                    .'<h2>ফেক অর্ডার কেন হয়</h2><h3>মূল কারণ</h3><ul><li>COD</li></ul><ol><li>চেক</li></ol>'
                    .'<p>অর্ডার নেওয়ার আগে নম্বর যাচাই করুন।</p>'
                    .'<h2>চেকলিস্ট</h2><p>OTP, ব্লক লিস্ট, এবং রিস্ক স্কোর দেখুন।</p>'
                    .'<section class="seo-ai-summary"><h2>এআই সারাংশ</h2><p>ফেক অর্ডার আটকাতে হিস্টোরি ও OTP একসাথে ব্যবহার করুন।</p></section>';

                $payload = [
                    'title' => 'ফেক অর্ডার কমানোর উপায়',
                    'slug' => 'fake-order-auto-guide',
                    'locale' => 'bn',
                    'focus_keyword' => 'ফেক অর্ডার',
                    'meta_title' => 'ফেক অর্ডার কমানোর উপায় | WooEasyLife',
                    'meta_description' => 'বাংলাদেশের COD সেলারদের জন্য ফেক অর্ডার কমানোর ব্যবহারিক গাইড এবং কুরিয়ার হিস্টোরি চেক।',
                    'excerpt' => 'ফেক অর্ডার কমাতে ব্যবহারিক ধাপ।',
                    'author_name' => 'Muhibbullah Ansary',
                    'robots' => 'index,follow',
                    'quick_answer' => 'ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি চেক করুন।',
                    'ai_search_summary' => 'ফেক অর্ডার আটকাতে হিস্টোরি ও OTP একসাথে ব্যবহার করুন।',
                    'body_html' => $body,
                    'faqs' => [
                        ['q' => 'ফেক অর্ডার কী?', 'a' => 'নেয় না এমন অর্ডার।'],
                        ['q' => 'হিস্টোরি কেন লাগে?', 'a' => 'ঝুঁকি বোঝার জন্য।'],
                        ['q' => 'শুরু কিভাবে?', 'a' => 'WooEasyLife দিয়ে।'],
                        ['q' => 'OTP লাগে?', 'a' => 'চেকআউট OTP ফেক অর্ডার কমায়।'],
                        ['q' => 'অটো এন্ট্রি?', 'a' => 'কনফার্ম হলেই কুরিয়ারে যায়।'],
                    ],
                    'seo_notes' => [],
                ];
            }

            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
            ], 200);
        });
    }

    public function test_options_include_auto_config(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson(route('blogAi.options'))
            ->assertOk()
            ->assertJsonPath('auto.enabled', true)
            ->assertJsonPath('auto.create_post', true)
            ->assertJsonPath('auto.soft_pass_score_cap', 59)
            ->assertJsonPath('auto.generate_image', false)
            ->assertJsonStructure(['auto' => ['require_queue', 'one_active_run_per_user', 'generate_image']]);
    }

    public function test_auto_pipeline_creates_draft_post_with_score(): void
    {
        $admin = $this->adminUser();
        $this->fakeAutoHttp();

        $response = $this->actingAs($admin)->postJson(route('blogAi.auto'), [
            'cluster' => 'fake_order',
            'seed_topic' => 'ফেক অর্ডার কমানো',
            'keywords_text' => "ফেক অর্ডার\nকুরিয়ার হিস্টোরি চেক",
            'create_post' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('queued', false)
            ->assertJsonPath('run.status', 'completed')
            ->assertJsonPath('run.needs_review', false)
            ->assertJsonStructure([
                'run' => ['id', 'progress_pct', 'live_score', 'step_log', 'score_breakdown'],
                'session' => ['id', 'draft'],
                'post_id',
            ]);

        $this->assertSame(100, (int) $response->json('run.progress_pct'));
        $this->assertGreaterThan(0, (int) $response->json('run.live_score'));
        $this->assertNotNull($response->json('post_id'));

        $post = BlogPost::query()->find($response->json('post_id'));
        $this->assertNotNull($post);
        $this->assertSame('draft', $post->status);
        $this->assertNotNull($post->ai_quality_score);
        $this->assertSame((int) $response->json('run.id'), (int) $post->ai_run_id);

        $run = BlogAiRun::query()->find($response->json('run.id'));
        $this->assertNotNull($run);
        $this->assertNotEmpty($run->step_log);
        $events = collect($run->step_log)->pluck('event')->all();
        $this->assertContains('reviewed', $events);
        $this->assertContains('completed', $events);
    }

    public function test_soft_pass_caps_score_and_marks_needs_review(): void
    {
        config([
            'blog_ai.min_body_words' => 5000,
            'blog_ai.auto.max_revisions_per_step' => 0,
            'blog_ai.auto.soft_pass_score_cap' => 59,
            'blog_ai.auto.allow_draft_soft_pass' => true,
        ]);

        $admin = $this->adminUser();
        $this->fakeAutoHttp();

        $response = $this->actingAs($admin)->postJson(route('blogAi.auto'), [
            'cluster' => 'fake_order',
            'keywords_text' => "ফেক অর্ডার\nকুরিয়ার হিস্টোরি চেক",
        ]);

        $response->assertOk()
            ->assertJsonPath('run.status', 'completed_needs_review')
            ->assertJsonPath('run.needs_review', true)
            ->assertJsonPath('run.soft_pass', true);

        $this->assertLessThanOrEqual(59, (int) $response->json('run.live_score'));
    }

    public function test_blocks_second_active_auto_run(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'auto_running',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'job_token' => 'tok',
        ]);

        $run = BlogAiRun::query()->create([
            'blog_ai_session_id' => $session->id,
            'user_id' => $admin->id,
            'mode' => 'auto',
            'status' => 'running',
            'current_step' => 'draft',
            'progress_pct' => 40,
            'live_score' => 50,
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.auto'), [
                'cluster' => 'fake_order',
                'keywords_text' => "ফেক অর্ডার\nকুরিয়ার",
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai', 'active_run_id'])
            ->assertJsonPath('errors.active_run_id.0', (string) $run->id);
    }

    public function test_options_exposes_active_run_id(): void
    {
        $admin = $this->adminUser();
        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'auto_running',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'job_token' => 'active-token',
        ]);
        $run = BlogAiRun::query()->create([
            'blog_ai_session_id' => $session->id,
            'user_id' => $admin->id,
            'mode' => 'auto',
            'status' => 'pending',
            'current_step' => 'queued',
            'progress_pct' => 0,
            'live_score' => 0,
        ]);

        $this->actingAs($admin)
            ->getJson(route('blogAi.options'))
            ->assertOk()
            ->assertJsonPath('auto.active_run_id', $run->id);
    }

    public function test_cancel_auto_run(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'auto_running',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'job_token' => 'cancel-token',
        ]);

        $run = BlogAiRun::query()->create([
            'blog_ai_session_id' => $session->id,
            'user_id' => $admin->id,
            'mode' => 'auto',
            'status' => 'running',
            'current_step' => 'outline',
            'progress_pct' => 30,
            'live_score' => 40,
            'step_log' => [],
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.runs.cancel', $run))
            ->assertOk()
            ->assertJsonPath('run.status', 'cancelled');

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertNotSame('cancel-token', $session->fresh()->job_token);
    }

    public function test_require_queue_blocks_when_forced(): void
    {
        config([
            'blog_ai.auto.require_queue' => true,
            'blog_ai.queue' => false,
        ]);

        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(route('blogAi.auto'), [
                'cluster' => 'fake_order',
                'keywords_text' => "ফেক অর্ডার\nকুরিয়ার",
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai']);
    }

    public function test_auto_pipeline_works_without_pasted_keywords(): void
    {
        $admin = $this->adminUser();
        $this->fakeAutoHttp();

        $this->actingAs($admin)
            ->postJson(route('blogAi.auto'), [
                'cluster' => 'fake_order',
                'seed_topic' => 'ফেক অর্ডার',
            ])
            ->assertOk()
            ->assertJsonPath('run.status', 'completed');
    }

    public function test_manual_session_store_still_requires_keywords(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(route('blogAi.store'), [
                'cluster' => 'general',
                'keywords_text' => '',
            ])
            ->assertStatus(422);
    }

    public function test_show_run_belongs_to_user(): void
    {
        $admin = $this->adminUser();
        $other = $this->adminUser();
        $this->fakeAutoHttp();

        $created = $this->actingAs($admin)->postJson(route('blogAi.auto'), [
            'cluster' => 'fake_order',
            'keywords_text' => "ফেক অর্ডার\nকুরিয়ার হিস্টোরি",
        ])->assertOk();

        $runId = $created->json('run.id');

        $this->actingAs($admin)
            ->getJson(route('blogAi.runs.show', $runId))
            ->assertOk()
            ->assertJsonPath('run.id', $runId);

        $this->actingAs($other)
            ->getJson(route('blogAi.runs.show', $runId))
            ->assertForbidden();
    }

    public function test_blog_index_includes_ai_quality_score(): void
    {
        $admin = $this->adminUser();

        BlogPost::query()->create([
            'title' => 'Test score post',
            'slug' => 'test-score-post',
            'locale' => 'bn',
            'status' => 'draft',
            'body_html' => '<p>Hello</p>',
            'ai_quality_score' => 84,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('blogPosts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BlogPosts/Index')
                ->has('posts', 1)
                ->where('posts.0.ai_quality_score', 84));
    }

    public function test_auto_job_recovers_draft_after_max_attempts_failure(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'auto_running',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'job_token' => 'recover-token',
            'draft_json' => [
                'title' => 'Recovered draft title',
                'body_html' => '<h2>এক</h2><p>Recover body with <a href="/">link</a>.</p>',
                'focus_keyword' => 'recover kw xyz',
                'slug' => 'recovered-draft-title',
                'locale' => 'bn',
                'faqs' => [['q' => 'q', 'a' => 'a']],
                'quality' => ['ai_ready' => false],
            ],
        ]);

        $run = BlogAiRun::query()->create([
            'blog_ai_session_id' => $session->id,
            'user_id' => $admin->id,
            'mode' => 'auto',
            'status' => 'running',
            'current_step' => 'image',
            'progress_pct' => 80,
            'live_score' => 55,
            'score_breakdown' => [
                'opportunity' => 70,
                'outline' => 72,
                'seo' => 50,
                'content' => 55,
                'image' => null,
            ],
            'step_log' => [],
            'input_json' => ['create_post' => true, 'soft_pass' => true],
        ]);

        $job = new \App\Jobs\ProcessBlogAutoPipeline($run->id, 'recover-token');
        $job->failed(new \Illuminate\Queue\MaxAttemptsExceededException(
            'App\Jobs\ProcessBlogAutoPipeline has been attempted too many times.'
        ));

        $run->refresh();
        $session->refresh();

        $this->assertContains($run->status, ['completed', 'completed_needs_review']);
        $this->assertNotNull($run->blog_post_id);
        $this->assertTrue((bool) data_get($run->input_json, 'interrupted_recovery'));
        $this->assertTrue((bool) data_get($run->input_json, 'image_skipped'));
        $this->assertNotSame('failed', $session->status);
        $this->assertNull($session->last_error);
    }

    public function test_queue_retry_after_exceeds_auto_job_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $timeout = (new \App\Jobs\ProcessBlogAutoPipeline(1))->timeout;

        $this->assertGreaterThan($timeout, $retryAfter);
    }
}
