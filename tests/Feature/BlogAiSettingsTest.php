<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BlogAi\BlogAiRuntimeConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BlogAiSettingsTest extends TestCase
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

    public function test_settings_page_renders(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('blogPosts.settings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('BlogPosts/Settings')
                ->has('settings')
                ->has('how_to')
                ->has('ops_notes'));
    }

    public function test_admin_can_toggle_prefer_gsc(): void
    {
        config(['blog_ai.auto.prefer_gsc' => true]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->put(route('blogPosts.settings.update'), [
                'prefer_gsc' => false,
                'enabled' => true,
                'smart_one_click' => true,
                'competitors_enabled' => true,
                'competitors_in_prompts' => true,
                'discovery_enabled' => true,
                'discovery_auto_on_smart' => true,
                'landing_ref_fetch' => true,
                'memory_enabled' => true,
                'memory_in_prompts' => true,
                'landing_public_base_url' => 'https://wooeasylife.com',
            ])
            ->assertRedirect(route('blogPosts.settings'));

        $this->assertFalse((bool) config('blog_ai.auto.prefer_gsc'));
        $this->assertSame('https://wooeasylife.com', config('blog_ai.landing_reference.public_base_url'));

        $snap = app(BlogAiRuntimeConfig::class)->snapshot();
        $this->assertSame('database', $snap['sources']['prefer_gsc']);
        $this->assertNotEmpty($snap['how_to']);
    }

    public function test_reset_clears_overrides(): void
    {
        $admin = $this->adminUser();
        app(BlogAiRuntimeConfig::class)->update(['prefer_gsc' => false]);

        $this->actingAs($admin)
            ->post(route('blogPosts.settings.reset'))
            ->assertRedirect(route('blogPosts.settings'));

        $snap = app(BlogAiRuntimeConfig::class)->snapshot();
        $this->assertSame('env', $snap['sources']['prefer_gsc']);
    }
}
