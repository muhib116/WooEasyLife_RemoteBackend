<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use App\Services\FraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantSteadfastFraudWhitelistTest extends TestCase
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

    public function test_fraud_check_skips_whitelist_when_merchant_has_steadfast_portal_credentials(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken('non-whitelisted.example.com');

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

        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('getReport')
                ->once()
                ->with('01712345678')
                ->andReturn([
                    'total_order' => 0,
                    'confirmed' => 0,
                    'frauds' => [],
                    'cancel' => 0,
                    'success_rate' => 'No order history found!',
                    'courier' => [],
                ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://non-whitelisted.example.com'))
            ->postJson('/api/fraud-check', [
                'phone' => '01712345678',
            ]);

        $response->assertOk();
    }

    public function test_fraud_check_blocked_without_whitelist_or_steadfast_credentials(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('non-whitelisted.example.com');

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://non-whitelisted.example.com'))
            ->postJson('/api/fraud-check', [
                'phone' => '01712345678',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'This domain is not allowed to use fraud check');
    }
}
