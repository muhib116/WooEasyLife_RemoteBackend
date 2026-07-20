<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\TutorialCategory;
use App\Models\TutorialVideo;
use App\Models\User;
use App\Services\TutorialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TutorialApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TutorialService::class)->forgetCache();
    }

    private function clearTutorials(): void
    {
        TutorialVideo::query()->delete();
        TutorialCategory::query()->delete();
        app(TutorialService::class)->forgetCache();
    }

    private function createMerchantWithToken(string $domain = 'shop.example.com'): array
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'test-token-' . bin2hex(random_bytes(16));

        AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Test Token',
                'token' => hash('sha256', $plainToken),
                'domain' => $domain,
                'status' => true,
            ]);
        });

        return [$user, $plainToken];
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $plainToken, string $origin = 'https://shop.example.com'): array
    {
        return [
            'Authorization' => 'Bearer ' . $plainToken,
            'Origin' => $origin,
        ];
    }

    public function test_get_tutorials_requires_authentication(): void
    {
        $this->getJson('/api/get-tutorials')
            ->assertUnauthorized();
    }

    public function test_get_tutorials_returns_category_keyed_video_lists(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->clearTutorials();

        $category = TutorialCategory::create([
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'sort_order' => 0,
        ]);

        TutorialVideo::create([
            'tutorial_category_id' => $category->id,
            'title' => '',
            'path' => 'https://www.youtube.com/watch?v=uFcrJJiDksY',
            'sort_order' => 0,
        ]);

        app(TutorialService::class)->forgetCache();

        $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/get-tutorials')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'dashboard' => [
                        ['title', 'path'],
                    ],
                ],
            ])
            ->assertJsonPath('data.dashboard.0.path', 'https://www.youtube.com/watch?v=uFcrJJiDksY')
            ->assertJsonPath('data.dashboard.0.title', '');
    }

    public function test_get_tutorials_falls_back_to_json_when_db_empty(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->clearTutorials();

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/get-tutorials')
            ->assertOk()
            ->assertJsonPath('status', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertArrayHasKey('dashboard', $data);
        $this->assertNotEmpty($data['dashboard']);
        $this->assertArrayHasKey('path', $data['dashboard'][0]);
    }

    public function test_get_tutorials_cache_is_busted_after_admin_update(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $admin = $this->createAdmin();
        $this->clearTutorials();

        $category = TutorialCategory::create([
            'key' => 'orders',
            'label' => 'Orders',
            'sort_order' => 0,
        ]);

        $video = TutorialVideo::create([
            'tutorial_category_id' => $category->id,
            'title' => '',
            'path' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            'sort_order' => 0,
        ]);

        $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/get-tutorials')
            ->assertJsonPath('data.orders.0.path', 'https://www.youtube.com/watch?v=aaaaaaaaaaa');

        $this->actingAs($admin)
            ->put(route('tutorials.videos.update', $video), [
                'tutorial_category_id' => $category->id,
                'title' => 'Updated',
                'path' => 'https://youtu.be/bbbbbbbbbbb',
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/get-tutorials')
            ->assertJsonPath('data.orders.0.path', 'https://youtu.be/bbbbbbbbbbb')
            ->assertJsonPath('data.orders.0.title', 'Updated');
    }

    public function test_admin_index_renders_and_preview_matches_db(): void
    {
        $admin = $this->createAdmin();
        $this->clearTutorials();

        $category = TutorialCategory::create([
            'key' => 'courierTab',
            'label' => 'Courier Tab',
            'sort_order' => 0,
        ]);

        TutorialVideo::create([
            'tutorial_category_id' => $category->id,
            'title' => 'Courier setup',
            'path' => 'https://www.youtube.com/watch?v=ccccccccccc',
            'sort_order' => 0,
        ]);

        // Poison the API cache with stale data — admin preview must ignore it.
        Cache::put('plugin_tutorials_payload', ['stale' => []], now()->addHour());

        $this->actingAs($admin)
            ->get(route('tutorials.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tutorials/Index')
                ->has('categories', 1)
                ->where('categories.0.key', 'courierTab')
                ->where('payloadPreview.courierTab.0.path', 'https://www.youtube.com/watch?v=ccccccccccc')
                ->missing('payloadPreview.stale')
                ->has('knownKeys')
            );
    }

    public function test_admin_can_crud_categories_and_videos(): void
    {
        $admin = $this->createAdmin();
        $this->clearTutorials();

        $this->actingAs($admin)
            ->post(route('tutorials.categories.store'), [
                'key' => 'smsConfigTab',
                'label' => 'SMS Config Tab',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tutorial_categories', [
            'key' => 'smsConfigTab',
            'label' => 'SMS Config Tab',
        ]);

        $category = TutorialCategory::where('key', 'smsConfigTab')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('tutorials.videos.store'), [
                'tutorial_category_id' => $category->id,
                'title' => '',
                'path' => 'https://www.youtube.com/watch?v=hxMNYkLN7tI',
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $video = TutorialVideo::firstOrFail();

        $this->actingAs($admin)
            ->put(route('tutorials.categories.update', $category), [
                'key' => 'smsConfigTab',
                'label' => 'SMS Config',
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tutorial_categories', [
            'id' => $category->id,
            'label' => 'SMS Config',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->delete(route('tutorials.videos.destroy', $video))
            ->assertRedirect();

        $this->assertDatabaseMissing('tutorial_videos', ['id' => $video->id]);

        $this->actingAs($admin)
            ->delete(route('tutorials.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('tutorial_categories', ['id' => $category->id]);
    }

    public function test_admin_rejects_non_youtube_video_urls(): void
    {
        $admin = $this->createAdmin();
        $this->clearTutorials();

        $category = TutorialCategory::create([
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('tutorials.index'))
            ->post(route('tutorials.videos.store'), [
                'tutorial_category_id' => $category->id,
                'title' => '',
                'path' => 'https://vimeo.com/123456',
                'sort_order' => 0,
            ])
            ->assertRedirect(route('tutorials.index'))
            ->assertSessionHasErrors('path');

        $this->assertDatabaseCount('tutorial_videos', 0);
    }

    public function test_admin_rejects_duplicate_category_keys(): void
    {
        $admin = $this->createAdmin();
        $this->clearTutorials();

        TutorialCategory::create([
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('tutorials.index'))
            ->post(route('tutorials.categories.store'), [
                'key' => 'dashboard',
                'label' => 'Another Dashboard',
                'sort_order' => 1,
            ])
            ->assertRedirect(route('tutorials.index'))
            ->assertSessionHasErrors('key');
    }

    public function test_merchant_cannot_access_tutorials_admin(): void
    {
        [$merchant] = $this->createMerchantWithToken();

        $this->actingAs($merchant)
            ->get(route('tutorials.index'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_guest_cannot_access_tutorials_admin(): void
    {
        $this->get(route('tutorials.index'))
            ->assertRedirect();
    }

    public function test_deleting_category_cascades_videos(): void
    {
        $admin = $this->createAdmin();
        $this->clearTutorials();

        $category = TutorialCategory::create([
            'key' => 'orders',
            'label' => 'Orders',
            'sort_order' => 0,
        ]);

        $video = TutorialVideo::create([
            'tutorial_category_id' => $category->id,
            'title' => '',
            'path' => 'https://www.youtube.com/watch?v=ddddddddddd',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('tutorials.categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('tutorial_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('tutorial_videos', ['id' => $video->id]);
    }

    public function test_youtube_url_helper_accepts_plugin_compatible_urls(): void
    {
        $this->assertTrue(TutorialService::isPlayableYoutubeUrl('https://www.youtube.com/watch?v=uFcrJJiDksY'));
        $this->assertTrue(TutorialService::isPlayableYoutubeUrl('https://youtu.be/uFcrJJiDksY'));
        $this->assertTrue(TutorialService::isPlayableYoutubeUrl('https://www.youtube.com/watch?v=uFcrJJiDksY&t=30'));
        $this->assertFalse(TutorialService::isPlayableYoutubeUrl('https://www.youtube.com/shorts/uFcrJJiDksY'));
        $this->assertFalse(TutorialService::isPlayableYoutubeUrl('https://vimeo.com/123'));
        $this->assertFalse(TutorialService::isPlayableYoutubeUrl(''));
    }

    public function test_migration_seeded_categories_cover_known_plugin_keys(): void
    {
        // RefreshDatabase runs migrations, which seed from tutorial.json + follow-up migrations.
        $keys = TutorialCategory::query()->pluck('key')->all();

        foreach (TutorialCategory::KNOWN_KEYS as $known) {
            $this->assertContains(
                $known,
                $keys,
                "Missing tutorial category for plugin route key: {$known}"
            );
        }
    }
}
