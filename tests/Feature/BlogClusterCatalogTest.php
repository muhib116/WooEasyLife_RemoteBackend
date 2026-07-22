<?php

namespace Tests\Feature;

use App\Models\BlogCluster;
use App\Models\BlogPost;
use App\Models\User;
use App\Services\BlogAi\BlogClusterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogClusterCatalogTest extends TestCase
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

    public function test_migration_seeds_topic_clusters(): void
    {
        $this->assertGreaterThanOrEqual(10, BlogCluster::query()->count());
        $this->assertDatabaseHas('blog_clusters', ['key' => 'fraud_checker']);
        $this->assertDatabaseHas('blog_clusters', ['key' => 'woocommerce']);
        $this->assertDatabaseHas('blog_clusters', ['key' => 'cod']);

        $catalog = app(BlogClusterCatalog::class);
        $seeds = $catalog->seedQueries('fraud_checker');
        $this->assertNotEmpty($seeds);
        $this->assertTrue(collect($seeds)->contains(fn ($s) => str_contains(mb_strtolower($s), 'fraud')));
    }

    public function test_admin_can_create_and_update_cluster(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post(route('blogPosts.clusters.store'), [
                'key' => 'new_topic_cluster',
                'label' => 'New Topic Cluster',
                'seed_queries_text' => "Topic A\nTopic B\n",
                'sort_order' => 50,
                'is_active' => true,
            ])
            ->assertRedirect(route('blogPosts.clusters.index'));

        $this->assertDatabaseHas('blog_clusters', [
            'key' => 'new_topic_cluster',
            'label' => 'New Topic Cluster',
        ]);

        $cluster = BlogCluster::query()->where('key', 'new_topic_cluster')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('blogPosts.clusters.update', $cluster), [
                'label' => 'Updated Cluster',
                'seed_queries_text' => "Topic C\n",
                'sort_order' => 20,
                'is_active' => true,
            ])
            ->assertRedirect(route('blogPosts.clusters.index'));

        $cluster->refresh();
        $this->assertSame('Updated Cluster', $cluster->label);
        $this->assertSame(['Topic C'], $cluster->seed_queries);

        app(BlogClusterCatalog::class)->forgetCache();
        $this->assertSame('Updated Cluster', app(BlogClusterCatalog::class)->label('new_topic_cluster'));
    }

    public function test_cluster_landing_and_detect_are_editable(): void
    {
        $admin = $this->adminUser();
        $cluster = BlogCluster::query()->where('key', 'fraud_checker')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('blogPosts.clusters.update', $cluster), [
                'label' => $cluster->label,
                'seed_queries_text' => "Best Fraud Checker BD\nFree Fraud Checker",
                'detect_needles_text' => "fraud checker\nফ্রড চেকার\ncustom needle xyz",
                'primary_path' => '/bd-fraud-checker',
                'related_paths_text' => "/pricing\n/return-loss-calculator",
                'must_link_paths_text' => "/bd-fraud-checker",
                'claims_text' => "ফোন নম্বর দিয়ে হিস্টোরি",
                'angle_hint' => 'Custom fraud angle',
                'seo_pages_text' => "bd_fraud_checker",
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('blogPosts.clusters.index'));

        app(BlogClusterCatalog::class)->forgetCache();
        $catalog = app(BlogClusterCatalog::class);

        $this->assertContains('custom needle xyz', $catalog->detectNeedles('fraud_checker'));
        $landing = $catalog->landing('fraud_checker');
        $this->assertSame('/bd-fraud-checker', $landing['primary_path'] ?? null);
        $this->assertSame('Custom fraud angle', $landing['angle_hint'] ?? null);

        $detected = app(\App\Services\BlogAi\BlogLandingContextService::class)
            ->detectCluster('please use custom needle xyz today');
        $this->assertSame('fraud_checker', $detected);
    }

    public function test_cannot_delete_cluster_used_by_posts(): void
    {
        $admin = $this->adminUser();
        $cluster = BlogCluster::query()->where('key', 'fake_order')->firstOrFail();

        BlogPost::query()->create([
            'title' => 'Uses cluster',
            'slug' => 'uses-cluster-post',
            'locale' => 'bn',
            'cluster' => 'fake_order',
            'article_type' => 'howto',
            'status' => 'draft',
            'excerpt' => 'Test',
            'meta_title' => 'Uses cluster',
            'meta_description' => str_repeat('meta ', 20),
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p>'.str_repeat('word ', 40).'</p>',
            'faqs_json' => [],
            'author_name' => 'Tester',
        ]);

        $this->actingAs($admin)
            ->from(route('blogPosts.clusters.index'))
            ->delete(route('blogPosts.clusters.destroy', $cluster))
            ->assertRedirect(route('blogPosts.clusters.index'))
            ->assertSessionHasErrors('cluster');

        $this->assertDatabaseHas('blog_clusters', ['key' => 'fake_order']);
    }

    public function test_blog_ai_and_clusters_pages_render(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('blogPosts.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('blogPosts.ai'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('blogPosts.seo'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('blogPosts.clusters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BlogPosts/Clusters')
                ->has('clusters'));
    }
}
