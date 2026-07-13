<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminMediaLibraryTest extends TestCase
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

    public function test_admin_can_view_media_library_index(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('mediaLibrary.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MediaLibrary/Index')
                ->has('items')
            );
    }

    public function test_admin_can_upload_image_as_webp(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('mediaLibrary.store'), [
            'file' => UploadedFile::fake()->image('hero.jpg', 800, 450),
            'title' => 'Hero banner',
            'alt' => 'Hero alt',
        ]);

        $response->assertOk();
        $response->assertJsonPath('media.title', 'Hero banner');
        $response->assertJsonPath('media.alt', 'Hero alt');
        $response->assertJsonPath('media.mime_type', 'image/webp');
        $response->assertJsonStructure(['media' => ['id', 'url', 'path'], 'url', 'path']);

        $path = $response->json('path');
        $this->assertNotEmpty($path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $this->assertDatabaseHas('media_items', [
            'title' => 'Hero banner',
            'alt' => 'Hero alt',
            'mime_type' => 'image/webp',
            'path' => $path,
        ]);
    }

    public function test_admin_can_list_update_and_delete_media(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $upload = $this->actingAs($admin)->postJson(route('mediaLibrary.store'), [
            'file' => UploadedFile::fake()->image('card.png', 400, 400),
            'title' => 'Card',
        ])->assertOk();

        $id = $upload->json('media.id');
        $path = $upload->json('path');

        $this->actingAs($admin)
            ->getJson(route('mediaLibrary.list'))
            ->assertOk()
            ->assertJsonFragment(['id' => $id, 'title' => 'Card']);

        $this->actingAs($admin)
            ->putJson(route('mediaLibrary.update', $id), [
                'title' => 'Card updated',
                'alt' => 'Updated alt',
            ])
            ->assertOk()
            ->assertJsonPath('media.title', 'Card updated')
            ->assertJsonPath('media.alt', 'Updated alt');

        $this->assertDatabaseHas('media_items', [
            'id' => $id,
            'title' => 'Card updated',
            'alt' => 'Updated alt',
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('mediaLibrary.destroy', $id))
            ->assertOk();

        $this->assertSoftDeleted('media_items', ['id' => $id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_guest_cannot_access_media_library(): void
    {
        $this->get(route('mediaLibrary.index'))->assertRedirect();
    }

    public function test_large_images_are_resized_before_store(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson(route('mediaLibrary.store'), [
            'file' => UploadedFile::fake()->image('wide.jpg', 3200, 1800),
        ]);

        $response->assertOk();
        $this->assertLessThanOrEqual(1920, (int) $response->json('media.width'));
        $this->assertLessThanOrEqual(1920, (int) $response->json('media.height'));
    }

    public function test_admin_can_fetch_image_from_url(): void
    {
        $admin = $this->adminUser();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        \Illuminate\Support\Facades\Http::fake([
            'https://cdn.example.com/*' => \Illuminate\Support\Facades\Http::response($png, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->actingAs($admin)->postJson(route('mediaLibrary.fetchUrl'), [
            'url' => 'https://cdn.example.com/pixel.png',
        ]);

        $response->assertOk();
        $response->assertJsonPath('mime', 'image/png');
        $response->assertJsonStructure(['filename', 'mime', 'data']);
        $this->assertSame($png, base64_decode($response->json('data')));
    }

    public function test_fetch_url_rejects_private_hosts(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->postJson(route('mediaLibrary.fetchUrl'), [
            'url' => 'http://127.0.0.1/secret.png',
        ])->assertStatus(422);
    }
}
