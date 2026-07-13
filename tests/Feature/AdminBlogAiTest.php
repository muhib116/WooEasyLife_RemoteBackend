<?php

namespace Tests\Feature;

use App\Models\BlogAiSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminBlogAiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000077',
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
            'blog_ai.require_pasted_keywords' => true,
        ]);
    }

    public function test_guest_cannot_access_blog_ai(): void
    {
        $this->getJson(route('blogAi.options'))->assertUnauthorized();
    }

    public function test_admin_can_load_options_and_create_session(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson(route('blogAi.options'))
            ->assertOk()
            ->assertJsonPath('default_locale', 'bn')
            ->assertJsonPath('require_pasted_keywords', true)
            ->assertJsonStructure(['clusters', 'enabled', 'queue']);

        $response = $this->actingAs($admin)->postJson(route('blogAi.store'), [
            'cluster' => 'fake_order',
            'seed_topic' => 'ফেক অর্ডার',
            'keywords_text' => "ফেক অর্ডার\nকুরিয়ার হিস্টোরি",
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('blog_ai_sessions', [
            'user_id' => $admin->id,
            'cluster' => 'fake_order',
            'status' => 'started',
        ]);
    }

    public function test_store_requires_pasted_keywords(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson(route('blogAi.store'), [
                'cluster' => 'general',
                'keywords_text' => '',
            ])
            ->assertStatus(422);
    }

    public function test_admin_can_generate_keywords_with_ai(): void
    {
        $admin = $this->adminUser();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'suggestqueries.google.com')) {
                return Http::response(['ফেক অর্ডার', ['ফেক অর্ডার কমানো', 'ফেক অর্ডার চেক']], 200);
            }

            return Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'keywords' => ['ফেক অর্ডার', 'কুরিয়ার হিস্টোরি চেক', 'COD fraud Bangladesh'],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 5,
                    'completion_tokens' => 10,
                    'total_tokens' => 15,
                ],
            ], 200);
        });

        $this->actingAs($admin)
            ->postJson(route('blogAi.suggestKeywords'), [
                'cluster' => 'fake_order',
                'seed_topic' => 'ফেক অর্ডার কমাতে',
            ])
            ->assertOk()
            ->assertJsonPath('keywords.0', 'ফেক অর্ডার')
            ->assertJsonStructure(['keywords', 'keywords_text', 'live_suggestions', 'usage']);

        $this->assertGreaterThan(0, BlogAiSession::dailyCalls($admin->id));
    }

    public function test_research_hooks_outline_draft_flow(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'started',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'seed_topic' => 'ফেক অর্ডার',
            'keywords_json' => ['pasted' => ['ফেক অর্ডার']],
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'suggestqueries.google.com')) {
                return Http::response(['ফেক অর্ডার', ['ফেক অর্ডার চেক', 'ফেক অর্ডার কমানোর উপায়']], 200);
            }

            $body = $request->data();
            $messages = $body['messages'] ?? [];
            $system = (string) ($messages[0]['content'] ?? '');

            if (str_contains($system, 'keyword planning')) {
                $payload = [
                    'primary' => 'ফেক অর্ডার',
                    'secondary' => ['কুরিয়ার হিস্টোরি'],
                    'suggestions' => [
                        ['keyword' => 'COD fraud check', 'intent' => 'informational', 'notes' => 'BD sellers'],
                    ],
                ];
            } elseif (str_contains($system, 'hook titles')) {
                $payload = [
                    'hooks' => [
                        [
                            'id' => 'h1',
                            'title' => 'ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি দেখুন',
                            'focus_keyword' => 'ফেক অর্ডার',
                            'angle' => 'howto',
                            'why_it_ranks' => 'High intent BD query',
                            'risk' => '',
                        ],
                    ],
                ];
            } elseif (str_contains($system, 'SEO outline')) {
                $payload = [
                    'h1' => 'ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি দেখুন',
                    'focus_keyword' => 'ফেক অর্ডার',
                    'slug_suggestion' => 'fake-order-courier-history',
                    'sections' => [
                        ['heading' => 'কেন ফেক অর্ডার হয়', 'bullets' => ['COD']],
                    ],
                    'faqs' => [],
                    'internal_links' => [
                        ['path' => '/bd-fraud-checker', 'anchor' => 'ফ্রড চেকার', 'reason' => 'tool'],
                        ['path' => '/', 'anchor' => 'WooEasyLife', 'reason' => 'home'],
                    ],
                    'cta' => 'WooEasyLife দিয়ে শুরু করুন',
                ];
            } else {
                $payload = [
                    'title' => 'ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি দেখুন',
                    'slug' => 'fake-order-courier-history',
                    'locale' => 'bn',
                    'focus_keyword' => 'ফেক অর্ডার',
                    'meta_title' => 'ফেক অর্ডার কমাতে কুরিয়ার হিস্টোরি | WooEasyLife',
                    'meta_description' => 'বাংলাদেশের COD সেলারদের জন্য ফেক অর্ডার কমানোর ব্যবহারিক গাইড এবং কুরিয়ার হিস্টোরি চেকের ধাপ।',
                    'excerpt' => 'ফেক অর্ডার কমাতে ব্যবহারিক ধাপ।',
                    'author_name' => 'Muhibbullah Ansary',
                    'robots' => 'index,follow',
                    'body_html' => '<h2>কেন ফেক অর্ডার হয়</h2><p>ফেক অর্ডার বাংলাদেশের COD ব্যবসায় বড় সমস্যা। <a href="/bd-fraud-checker">ফ্রড চেকার</a> দিয়ে হিস্টোরি দেখুন। <a href="/">WooEasyLife</a> অপারেশন সহজ করে।</p>',
                    'faqs' => [
                        ['q' => 'ফেক অর্ডার কী?', 'a' => 'COD অর্ডার যেখানে কাস্টমার নেয় না।'],
                    ],
                    'seo_notes' => [],
                ];
            }

            return Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
                ]],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ], 200);
        });

        $this->actingAs($admin)
            ->postJson(route('blogAi.research', $session), [
                'keywords_text' => 'ফেক অর্ডার',
            ])
            ->assertOk()
            ->assertJsonPath('queued', false)
            ->assertJsonPath('session.keywords.primary', 'ফেক অর্ডার')
            ->assertJsonPath('session.keywords.live_suggestions.0', 'ফেক অর্ডার চেক')
            ->assertJsonPath('session.status', 'keywords_ready');

        $this->actingAs($admin)
            ->postJson(route('blogAi.hooks', $session))
            ->assertOk()
            ->assertJsonPath('session.status', 'hooks_ready')
            ->assertJsonPath('session.hooks.0.id', 'h1')
            ->assertJsonPath('session.usage.ai_calls', 2);

        $this->actingAs($admin)
            ->postJson(route('blogAi.outline', $session), [
                'selected_hook_ids' => ['h1'],
            ])
            ->assertOk()
            ->assertJsonPath('session.status', 'outline_ready');

        $this->actingAs($admin)
            ->postJson(route('blogAi.draft', $session))
            ->assertOk()
            ->assertJsonPath('session.status', 'draft_ready')
            ->assertJsonPath('session.draft.slug', 'fake-order-courier-history')
            ->assertJsonPath('session.draft.locale', 'bn')
            ->assertJsonPath('session.draft.status', 'draft')
            ->assertJsonPath('session.draft.faqs.0.q', 'ফেক অর্ডার কী?');

        $session->refresh();
        $this->assertGreaterThan(0, $session->ai_calls);
        $this->assertNull($session->resume_status);
        $this->assertGreaterThan(0, BlogAiSession::dailyCalls($admin->id));
    }

    public function test_requires_openai_key_for_research(): void
    {
        config([
            'landing.openai_api_key' => null,
            'blog_ai.enabled' => true,
            'blog_ai.queue' => false,
        ]);

        $admin = $this->adminUser();
        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'started',
            'locale' => 'bn',
            'cluster' => 'general',
            'keywords_json' => ['pasted' => ['test']],
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.research', $session), [
                'keywords_text' => 'test',
            ])
            ->assertStatus(422);

        $session->refresh();
        $this->assertSame('failed', $session->status);
        $this->assertNotNull($session->last_error);
    }

    public function test_daily_call_cap_is_enforced(): void
    {
        $admin = $this->adminUser();
        config(['blog_ai.daily_ai_calls_cap' => 1]);

        \Illuminate\Support\Facades\Cache::put(
            BlogAiSession::dailyCallsKey($admin->id),
            1,
            now()->addDay(),
        );

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'keywords_ready',
            'locale' => 'bn',
            'cluster' => 'general',
            'keywords_json' => ['pasted' => ['ফেক অর্ডার'], 'primary' => 'ফেক অর্ডার'],
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.hooks', $session))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ai']);
    }

    public function test_stale_busy_session_can_be_recovered(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'generating_draft',
            'locale' => 'bn',
            'cluster' => 'general',
            'resume_status' => 'outline_ready',
            'keywords_json' => ['pasted' => ['ফেক অর্ডার'], 'primary' => 'ফেক অর্ডার'],
        ]);

        $session->updated_at = now()->subMinutes(20);
        $session->saveQuietly();

        $this->actingAs($admin)
            ->postJson(route('blogAi.recover', $session))
            ->assertOk()
            ->assertJsonPath('session.status', 'failed');

        $session->refresh();
        $this->assertSame('failed', $session->status);
        $this->assertNotNull($session->last_error);
        $this->assertNotNull($session->job_token);
    }

    public function test_force_unlock_invalidates_job_token(): void
    {
        $admin = $this->adminUser();

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'generating_draft',
            'locale' => 'bn',
            'cluster' => 'general',
            'job_token' => 'token-old',
            'resume_status' => 'outline_ready',
            'keywords_json' => ['pasted' => ['ফেক অর্ডার'], 'primary' => 'ফেক অর্ডার'],
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.recover', $session))
            ->assertOk()
            ->assertJsonPath('session.status', 'failed');

        $session->refresh();
        $this->assertNotSame('token-old', $session->job_token);
    }

    public function test_image_step_stores_media_and_sets_og_image(): void
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD WebP required for image step.');
        }

        $admin = $this->adminUser();

        $img = imagecreatetruecolor(64, 64);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        $session = BlogAiSession::query()->create([
            'user_id' => $admin->id,
            'status' => 'draft_ready',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'draft_json' => [
                'title' => 'ফেক অর্ডার গাইড',
                'focus_keyword' => 'ফেক অর্ডার',
                'slug' => 'fake-order-guide',
                'body_html' => '<p>test</p>',
            ],
        ]);

        Http::fake([
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode($png)]],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->postJson(route('blogAi.image', $session))
            ->assertOk()
            ->assertJsonPath('session.status', 'image_ready');

        $session->refresh();
        $this->assertNotEmpty($session->image_json['media_id'] ?? null);
        $this->assertNotEmpty($session->draft_json['og_image'] ?? null);
        $this->assertSame(1, (int) $session->ai_calls);
    }
}
