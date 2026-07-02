<?php

namespace Tests\Feature;

use App\Models\PluginsVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * @group plugin-upload-api
 */
class PluginUploadApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test-plugin-upload-key';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.plugin_upload.api_key', self::API_KEY);
    }

    private function authHeaders(?string $token = self::API_KEY): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    private function validSettings(string $version = '1.4.0'): string
    {
        return json_encode([
            'name' => 'WooEasyLife',
            'slug' => 'woo-easy-life',
            'version' => $version,
            'download_url' => 'https://api.wpsalehub.com/download-plugins',
            'requires' => [
                'wordpress' => '5.0',
                'php' => '7.4',
                'woocommerce' => '5.0',
            ],
        ]);
    }

    public function test_upload_requires_api_key(): void
    {
        $response = $this->post('/api/admin/plugins/versions', [
            'version' => '1.4.0',
            'settings' => $this->validSettings(),
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized',
            ]);
    }

    public function test_upload_rejects_invalid_api_key(): void
    {
        $response = $this->withHeaders($this->authHeaders('wrong-key'))
            ->post('/api/admin/plugins/versions', [
                'version' => '1.4.0',
                'settings' => $this->validSettings(),
            ]);

        $response->assertUnauthorized()
            ->assertJson([
                'status' => false,
                'message' => 'Unauthorized',
            ]);
    }

    public function test_upload_returns_service_unavailable_when_key_not_configured(): void
    {
        Config::set('services.plugin_upload.api_key', '');

        $response = $this->withHeaders($this->authHeaders(''))
            ->post('/api/admin/plugins/versions');

        $response->assertStatus(503)
            ->assertJson([
                'status' => false,
                'message' => 'Plugin upload API is not configured',
            ]);
    }

    public function test_upload_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/admin/plugins/versions');

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
                'message' => 'Validation Failed',
            ])
            ->assertJsonStructure([
                'errors' => ['version', 'settings', 'file'],
            ]);
    }

    public function test_upload_rejects_duplicate_version(): void
    {
        PluginsVersion::create([
            'version' => '1.4.0',
            'path' => 'app/private/wpsalehub-1.4.0.zip',
            'download_count' => 0,
            'settings' => ['name' => 'WooEasyLife'],
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/admin/plugins/versions', [
                'version' => '1.4.0',
                'settings' => $this->validSettings(),
                'file' => UploadedFile::fake()->create('plugin.zip', 100, 'application/zip'),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.version', fn ($value) => str_contains((string) $value, 'taken'));
    }

    public function test_upload_creates_plugin_version(): void
    {
        $zip = UploadedFile::fake()->create('plugin.zip', 100, 'application/zip');

        $response = $this->withHeaders($this->authHeaders())
            ->post('/api/admin/plugins/versions', [
                'version' => '1.4.0',
                'settings' => $this->validSettings(),
                'file' => $zip,
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => true,
                'message' => 'Version created successfully',
            ])
            ->assertJsonPath('data.version', '1.4.0')
            ->assertJsonPath('data.path', 'app/private/wpsalehub-1.4.0.zip')
            ->assertJsonPath('data.download_count', 0)
            ->assertJsonPath('data.settings.name', 'WooEasyLife');

        $this->assertDatabaseHas('plugins_versions', [
            'version' => '1.4.0',
            'path' => 'app/private/wpsalehub-1.4.0.zip',
        ]);

        $this->assertFileExists(storage_path('app/private/wpsalehub-1.4.0.zip'));
    }

    public function test_uploaded_version_becomes_latest_metadata(): void
    {
        $this->withHeaders($this->authHeaders())
            ->post('/api/admin/plugins/versions', [
                'version' => '1.5.0',
                'settings' => $this->validSettings('1.5.0'),
                'file' => UploadedFile::fake()->create('plugin.zip', 100, 'application/zip'),
            ])
            ->assertCreated();

        $this->getJson('/get-metadata')
            ->assertOk()
            ->assertJsonPath('version', '1.5.0')
            ->assertJsonPath('name', 'WooEasyLife');
    }
}
