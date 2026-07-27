<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\CacheRuntimeConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CacheRuntimeConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CacheRuntimeConfig::clearMemoForTests();

        PlatformSetting::query()->where('key', CacheRuntimeConfig::SETTING_KEY)->delete();
    }

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Cache Admin',
            'email' => 'cache-admin-'.uniqid().'@example.com',
            'phone' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_default_cache_driver_is_database(): void
    {
        $this->assertSame('database', config('cache.default'));
        $this->assertContains(config('cache.default'), CacheRuntimeConfig::DRIVERS);
    }

    public function test_admin_can_override_cache_driver(): void
    {
        $service = app(CacheRuntimeConfig::class);

        $snapshot = $service->update('file');

        $this->assertSame('file', $snapshot['driver']);
        $this->assertSame('database', $snapshot['source']);
        $this->assertSame('file', config('cache.default'));
        $this->assertDatabaseHas('platform_settings', [
            'key' => CacheRuntimeConfig::SETTING_KEY,
        ]);

        $reset = $service->resetToEnv();
        $this->assertSame($reset['env_default'], $reset['driver']);
        $this->assertDatabaseMissing('platform_settings', [
            'key' => CacheRuntimeConfig::SETTING_KEY,
        ]);
    }

    public function test_maintenance_endpoints_update_and_reset_cache_driver(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->putJson(route('maintenance.cacheDriver.update'), ['driver' => 'file'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cache.driver', 'file')
            ->assertJsonPath('cache.source', 'database');

        $this->actingAs($admin)
            ->getJson(route('maintenance.status'))
            ->assertOk()
            ->assertJsonPath('cache.driver', 'file');

        $this->actingAs($admin)
            ->postJson(route('maintenance.cacheDriver.reset'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cache.driver', 'database');
    }

    public function test_invalid_cache_driver_is_rejected(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->putJson(route('maintenance.cacheDriver.update'), ['driver' => 'array'])
            ->assertStatus(422);
    }
}
