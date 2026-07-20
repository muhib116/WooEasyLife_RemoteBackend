<?php

namespace Tests\Feature;

use App\Models\BlogAiMemory;
use App\Models\BlogLearningInsight;
use App\Models\User;
use App\Services\BlogAi\BlogIntelligenceScorer;
use App\Services\BlogAi\BlogMemoryService;
use App\Services\BlogAi\BlogProductBriefBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-mem-'.uniqid().'@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_manual_memory_is_injected_into_product_brief(): void
    {
        config([
            'blog_ai.memory.enabled' => true,
            'blog_ai.memory.in_prompts' => true,
            'blog_ai.analytics.learning_in_prompts' => false,
        ]);

        app(BlogMemoryService::class)->upsert([
            'type' => BlogAiMemory::TYPE_INSTRUCTION,
            'content' => 'Always mention BD COD sellers in the intro',
            'priority' => 90,
            'source' => BlogAiMemory::SOURCE_MANUAL,
        ]);

        $brief = app(BlogProductBriefBuilder::class)->build('fake_order');

        $this->assertSame('ready', $brief['standing_memory']['status'] ?? null);
        $this->assertNotEmpty($brief['standing_memory']['instructions'] ?? []);
        $this->assertStringContainsString(
            'Always mention BD COD sellers',
            $brief['standing_memory']['instructions'][0]['content'] ?? '',
        );
    }

    public function test_absorb_from_learning_creates_memories(): void
    {
        BlogLearningInsight::query()->create([
            'scope' => 'global',
            'generated_at' => now(),
            'summary_bn' => 'টেস্ট',
            'posts_analyzed' => 2,
            'events_analyzed' => 5,
            'payload_json' => [
                'winning_keywords' => ['ফেক অর্ডার', 'ফ্রড চেকার'],
                'underperforming_topics' => [
                    ['focus_keyword' => 'generic ecommerce tips', 'title' => 'Weak'],
                ],
                'next_post_ideas' => [
                    ['cluster' => 'fake_order', 'seed_topic' => 'COD fraud checklist', 'reason' => 'gsc'],
                ],
                'writing_guidance' => ['Prefer striking-distance GSC queries'],
                'gsc_keyword_seeds' => [
                    ['query' => 'কুরিয়ার হিস্টোরি চেক', 'bucket' => 'striking_distance'],
                ],
            ],
        ]);

        $result = app(BlogMemoryService::class)->absorbFromInsight();

        $this->assertGreaterThan(0, $result['created']);
        $this->assertDatabaseHas('blog_ai_memories', [
            'type' => BlogAiMemory::TYPE_KEYWORD_PREFER,
            'source' => BlogAiMemory::SOURCE_LEARNING,
        ]);
        $this->assertDatabaseHas('blog_ai_memories', [
            'type' => BlogAiMemory::TYPE_LESSON,
            'source' => BlogAiMemory::SOURCE_LEARNING,
        ]);
    }

    public function test_admin_can_crud_memories(): void
    {
        $admin = $this->adminUser();

        $create = $this->actingAs($admin)->postJson(route('blogAi.memories.store'), [
            'type' => 'brand_note',
            'content' => 'Never invent courier partnership claims',
            'priority' => 85,
        ]);

        $create->assertCreated()->assertJsonPath('ok', true);
        $id = $create->json('item.id');

        $this->actingAs($admin)
            ->putJson(route('blogAi.memories.update', $id), ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('item.is_active', false);

        $this->actingAs($admin)
            ->getJson(route('blogAi.memories.index'))
            ->assertOk()
            ->assertJsonStructure(['items', 'stats', 'intelligence']);

        $this->actingAs($admin)
            ->deleteJson(route('blogAi.memories.destroy', $id))
            ->assertOk();

        $this->assertDatabaseMissing('blog_ai_memories', ['id' => $id]);
    }

    public function test_intelligence_includes_memory_dimension(): void
    {
        $keys = collect(app(BlogIntelligenceScorer::class)->score()['dimensions'])->pluck('key')->all();
        $this->assertContains('memory', $keys);
    }
}
