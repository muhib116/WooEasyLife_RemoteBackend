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

    public function test_published_post_without_published_at_is_public(): void
    {
        $admin = $this->adminUser();

        $post = BlogPost::create([
            'title' => 'Live Without Timestamp',
            'slug' => 'live-without-timestamp',
            'locale' => 'bn',
            'status' => 'published',
            'published_at' => null,
            'meta_description' => 'Published CMS post missing published_at still loads publicly.',
            'body_html' => '<p>Visible on /blog</p>',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->get('/blog/live-without-timestamp')->assertOk();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/blog/live-without-timestamp', false);

        $this->actingAs($admin)
            ->get(route('blogPosts.edit', $post))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BlogPosts/Form')
                ->where('post.public_path', '/blog/live-without-timestamp')
                ->where('post.public_url', fn ($url) => is_string($url) && str_contains($url, '/blog/live-without-timestamp'))
            );
    }

    public function test_future_published_at_still_public_when_status_published(): void
    {
        $admin = $this->adminUser();

        BlogPost::create([
            'title' => 'Future Timestamp Post',
            'slug' => 'future-timestamp-post',
            'locale' => 'bn',
            'status' => 'published',
            'published_at' => now()->addDay(),
            'meta_description' => 'Status published wins over a future published_at for public visibility.',
            'body_html' => '<p>Should be public</p>',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->get('/blog/future-timestamp-post')->assertOk();
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
            'body_html' => '<p>Hello</p><script>alert(1)</script><p><a href="/bd-fraud-checker">Fraud checker</a></p><p><a href="javascript:alert(1)">x</a></p>',
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

    public function test_publish_blocks_duplicate_focus_keyword(): void
    {
        $admin = $this->adminUser();

        BlogPost::create([
            'title' => 'First Post',
            'slug' => 'first-focus-post',
            'locale' => 'bn',
            'status' => 'published',
            'focus_keyword' => 'ফেক অর্ডার',
            'body_html' => '<p><a href="/bd-fraud-checker">check</a></p>',
            'published_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'Second Post',
            'slug' => 'second-focus-post',
            'locale' => 'bn',
            'status' => 'published',
            'focus_keyword' => 'ফেক অর্ডার',
            'meta_description' => 'Duplicate focus keyword should be blocked on publish for same locale.',
            'body_html' => '<h2>x</h2><p><a href="/">home</a></p>',
        ])->assertSessionHasErrors('focus_keyword');
    }

    public function test_publish_requires_internal_link(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('blogPosts.store'), [
            'title' => 'No Link Post',
            'slug' => 'no-link-post',
            'locale' => 'en',
            'status' => 'published',
            'meta_description' => 'Publishing without an internal link should fail validation.',
            'body_html' => '<h2>Hello</h2><p>No internal URL here.</p>',
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
