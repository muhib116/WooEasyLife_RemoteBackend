<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBlogPostTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_admin_can_view_blog_posts_index(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('blogPosts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('BlogPosts/Index')
                ->has('posts')
            );
    }

    public function test_admin_can_create_and_publish_blog_post(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'COD Fraud Check Guide',
            'slug' => 'cod-fraud-check-guide',
            'locale' => 'en',
            'status' => 'published',
            'excerpt' => 'How COD sellers reduce fake orders.',
            'meta_title' => 'COD Fraud Check Guide | WooEasyLife',
            'meta_description' => 'Practical COD fraud check steps for Bangladesh sellers.',
            'focus_keyword' => 'cod fraud check',
            'robots' => 'index,follow',
            'author_name' => 'WooEasyLife',
            'body_html' => '<h2>Start here</h2><p>Check courier history before confirm.</p><p><a href="/bd-fraud-checker">Fraud checker</a></p>',
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('blog_posts', [
            'slug' => 'cod-fraud-check-guide',
            'status' => 'published',
            'locale' => 'en',
        ]);

        $this->get('/blog/cod-fraud-check-guide')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.slug', 'cod-fraud-check-guide')
                ->where('post.title', 'COD Fraud Check Guide')
            );

        $this->get('/en/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Index')
                ->has('posts')
                ->where('posts', fn ($posts) => collect($posts)->contains(
                    fn ($post) => ($post['slug'] ?? null) === 'cod-fraud-check-guide'
                ))
            );
    }

    public function test_draft_posts_are_hidden_from_public_blog(): void
    {
        $admin = $this->adminUser();

        BlogPost::create([
            'title' => 'Secret Draft',
            'slug' => 'secret-draft',
            'locale' => 'bn',
            'status' => 'draft',
            'body_html' => '<p>Hidden</p>',
            'published_at' => null,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->get('/blog/secret-draft')->assertNotFound();
    }

    public function test_markdown_posts_still_load(): void
    {
        $this->get('/blog')->assertOk();
        $this->get('/blog/fake-order-komano')->assertOk();
    }

    public function test_publish_requires_readable_english_slug(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'কিভাবে ফেক অর্ডার আটকাবো',
            'slug' => '',
            'locale' => 'bn',
            'status' => 'published',
            'body_html' => '<p>Test</p>',
        ])->assertSessionHasErrors('slug');
    }

    public function test_script_tags_are_stripped_from_body(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'Safe HTML Post',
            'slug' => 'safe-html-post',
            'locale' => 'en',
            'status' => 'published',
            'meta_description' => 'Sanitized blog body for COD sellers in Bangladesh.',
            'body_html' => '<p>Hello</p><script>alert(1)</script><p><a href="javascript:alert(1)">x</a></p>',
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $post = BlogPost::query()->where('slug', 'safe-html-post')->first();
        $this->assertNotNull($post);
        $this->assertStringNotContainsString('<script', $post->body_html);
        $this->assertStringNotContainsString('javascript:', $post->body_html);

        $this->get('/blog/safe-html-post')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Show')
                ->where('post.html', fn ($html) => ! str_contains((string) $html, '<script')
                    && ! str_contains((string) $html, 'javascript:'))
            );
    }

    public function test_body_html_has_size_limit(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'Too Big',
            'slug' => 'too-big-post',
            'locale' => 'en',
            'status' => 'draft',
            'body_html' => str_repeat('a', 200001),
        ])->assertSessionHasErrors('body_html');
    }

    public function test_admin_can_upload_blog_image(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('blogPosts.uploadImage'), [
            'upload' => UploadedFile::fake()->image('hero.jpg', 800, 450),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path']);
        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_guest_cannot_access_blog_cms(): void
    {
        $this->get(route('blogPosts.index'))->assertRedirect();
    }
}
