<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourierConfigurationSteadfastTest extends TestCase
{
    use RefreshDatabase;

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

    private function apiHeaders(string $plainToken, string $origin): array
    {
        return [
            'Authorization' => 'Bearer ' . $plainToken,
            'Origin' => $origin,
        ];
    }

    public function test_steadfast_configuration_can_save_optional_portal_credentials(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'api-key-123',
                'secret_key' => 'secret-key-456',
                'is_active' => true,
                'settings' => [
                    'username' => 'merchant@steadfast.test',
                    'password' => 'portal-password',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $configuration = CourierConfiguration::query()
            ->where('user_id', $user->id)
            ->where('slug', 'steadfast')
            ->first();

        $this->assertNotNull($configuration);
        $this->assertSame('merchant@steadfast.test', $configuration->settings['username'] ?? null);
        $this->assertSame('portal-password', $configuration->settings['password'] ?? null);
    }

    public function test_steadfast_configuration_preserves_password_when_not_resent(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $configuration = CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'api-key-123',
            'secret_key' => 'secret-key-456',
            'is_active' => true,
            'settings' => [
                'username' => 'merchant@steadfast.test',
                'password' => 'portal-password',
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/save-configuration', [
                'id' => $configuration->id,
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'api-key-123',
                'secret_key' => 'secret-key-456',
                'is_active' => true,
                'settings' => [
                    'username' => 'updated@steadfast.test',
                    'password' => '',
                ],
            ]);

        $response->assertOk();

        $configuration->refresh();

        $this->assertSame('updated@steadfast.test', $configuration->settings['username'] ?? null);
        $this->assertSame('portal-password', $configuration->settings['password'] ?? null);
    }

    public function test_steadfast_configuration_masks_password_on_get(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'api-key-123',
            'secret_key' => 'secret-key-456',
            'is_active' => true,
            'settings' => [
                'username' => 'merchant@steadfast.test',
                'password' => 'portal-password',
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/get-configuration');

        $response->assertOk()
            ->assertJsonPath('data.steadfast.settings.username', 'merchant@steadfast.test')
            ->assertJsonPath('data.steadfast.settings.password', '');
    }

    public function test_steadfast_configuration_update_clears_cached_fraud_session(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $configuration = CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'api-key-123',
            'secret_key' => 'secret-key-456',
            'is_active' => true,
            'settings' => [
                'username' => 'merchant@steadfast.test',
                'password' => 'portal-password',
            ],
        ]);

        $oldCredentials = [
            'username' => 'merchant@steadfast.test',
            'password' => 'portal-password',
        ];

        Cache::put(
            \App\Services\FraudCheck\SteadfastFraudChecker::sessionCacheKeyFor($oldCredentials),
            ['host' => 'www.steadfast.com.bd', 'cookies' => ['steadfast_courier_session' => 'cached']],
            now()->addHour(),
        );

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/save-configuration', [
                'id' => $configuration->id,
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'api-key-123',
                'secret_key' => 'secret-key-456',
                'is_active' => true,
                'settings' => [
                    'username' => 'merchant@steadfast.test',
                    'password' => 'new-portal-password',
                ],
            ])
            ->assertOk();

        $this->assertFalse(Cache::has(
            \App\Services\FraudCheck\SteadfastFraudChecker::sessionCacheKeyFor($oldCredentials)
        ));
    }
}
