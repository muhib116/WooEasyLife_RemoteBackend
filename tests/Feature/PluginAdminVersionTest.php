<?php

namespace Tests\Feature;

use App\Models\PluginsVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PluginAdminVersionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Plugin Admin',
            'email' => 'plugin-admin-'.uniqid().'@example.com',
            'phone' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    private function settingsJson(string $version = '1.6.5'): string
    {
        return json_encode([
            'name' => 'WooEasyLife',
            'slug' => 'woo-easy-life',
            'version' => $version,
            'download_url' => 'https://api.wpsalehub.com/download-plugins',
        ], JSON_THROW_ON_ERROR);
    }

    private function pluginZip(string $name = 'plugin.zip'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/zip');
    }

    private function seedVersion(
        string $version = '1.6.5',
        int $downloads = 12,
        ?int $createdBy = null
    ): PluginsVersion {
        return PluginsVersion::create([
            'version' => $version,
            'path' => 'app/private/wpsalehub-'.$version.'.zip',
            'download_count' => $downloads,
            'created_by' => $createdBy,
            'settings' => json_decode($this->settingsJson($version), true),
        ]);
    }

    public function test_admin_can_update_plugin_version_without_replacing_the_zip(): void
    {
        $admin = $this->admin();
        $plugin = $this->seedVersion('1.6.5', 12, $admin->id);

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.5',
                'settings' => $this->settingsJson('1.6.5'),
            ])
            ->assertRedirect(route('plugins.index'));

        $plugin->refresh();

        $this->assertSame('1.6.5', $plugin->version);
        $this->assertSame(12, (int) $plugin->download_count);
        $this->assertSame($admin->id, $plugin->created_by);
        $this->assertSame('app/private/wpsalehub-1.6.5.zip', $plugin->path);
        $this->assertSame('WooEasyLife', $plugin->settings['name'] ?? null);
    }

    public function test_admin_can_replace_plugin_zip_without_resetting_downloads(): void
    {
        $admin = $this->admin();
        $plugin = $this->seedVersion('1.6.5', 67, $admin->id);

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.6',
                'settings' => $this->settingsJson('1.6.6'),
                'file' => $this->pluginZip('woo-easy-life.zip'),
            ])
            ->assertRedirect(route('plugins.index'));

        $plugin->refresh();

        $this->assertSame('1.6.6', $plugin->version);
        $this->assertSame(67, (int) $plugin->download_count);
        $this->assertSame('app/private/wpsalehub-1.6.6.zip', $plugin->path);
        $this->assertFileExists(storage_path('app/private/wpsalehub-1.6.6.zip'));
    }

    public function test_update_rejects_invalid_settings_json(): void
    {
        $admin = $this->admin();
        $plugin = $this->seedVersion();

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.5',
                'settings' => '{not-json',
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHasErrors('settings');

        $this->assertSame('WooEasyLife', $plugin->fresh()->settings['name'] ?? null);
    }

    public function test_update_allows_version_when_only_a_soft_deleted_row_conflicts(): void
    {
        $admin = $this->admin();
        $deleted = $this->seedVersion('1.6.5', 3, $admin->id);
        $deleted->delete();

        $plugin = $this->seedVersion('1.6.4', 9, $admin->id);

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.5',
                'settings' => $this->settingsJson('1.6.5'),
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('1.6.5', $plugin->fresh()->version);
    }

    public function test_create_rejects_non_zip_uploads(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.createVersion'), [
                'version' => '1.6.7',
                'settings' => $this->settingsJson('1.6.7'),
                'file' => UploadedFile::fake()->create('plugin.txt', 20, 'text/plain'),
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHasErrors('file');
    }

    public function test_guest_cannot_update_plugin_version(): void
    {
        $plugin = $this->seedVersion();

        $this->post(route('plugins.updateVersion', $plugin->id), [
            'version' => '1.6.9',
            'settings' => $this->settingsJson('1.6.9'),
        ])->assertRedirect();

        $this->assertSame('1.6.5', $plugin->fresh()->version);
    }

    public function test_merchant_cannot_update_plugin_version(): void
    {
        $plugin = $this->seedVersion();
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'plugin-merchant-'.uniqid().'@example.com',
            'phone' => '018'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($merchant)
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.9',
                'settings' => $this->settingsJson('1.6.9'),
            ])
            ->assertRedirect(route('portal.dashboard'));

        $this->assertSame('1.6.5', $plugin->fresh()->version);
    }

    public function test_update_rejects_another_live_version_number(): void
    {
        $admin = $this->admin();
        $this->seedVersion('1.6.5', 4, $admin->id);
        $plugin = $this->seedVersion('1.6.4', 8, $admin->id);

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.5',
                'settings' => $this->settingsJson('1.6.5'),
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHasErrors('version');

        $this->assertSame('1.6.4', $plugin->fresh()->version);
    }

    public function test_admin_can_create_plugin_version(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('plugins.index'))
            ->post(route('plugins.createVersion'), [
                'version' => '1.6.8',
                'settings' => $this->settingsJson('1.6.8'),
                'file' => $this->pluginZip('woo-easy-life.zip'),
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('plugins_versions', [
            'version' => '1.6.8',
            'path' => 'app/private/wpsalehub-1.6.8.zip',
            'created_by' => $admin->id,
        ]);
        $this->assertFileExists(storage_path('app/private/wpsalehub-1.6.8.zip'));
    }

    public function test_in_place_update_keeps_version_as_latest_metadata(): void
    {
        $admin = $this->admin();
        $plugin = $this->seedVersion('1.6.5', 12, $admin->id);

        $this->actingAs($admin)
            ->post(route('plugins.updateVersion', $plugin->id), [
                'version' => '1.6.5',
                'settings' => $this->settingsJson('1.6.5'),
                'file' => $this->pluginZip('woo-easy-life.zip'),
            ])
            ->assertRedirect(route('plugins.index'));

        $this->getJson('/get-metadata')
            ->assertOk()
            ->assertJsonPath('version', '1.6.5')
            ->assertJsonPath('name', 'WooEasyLife');

        $this->assertSame(12, (int) $plugin->fresh()->download_count);
    }
}
