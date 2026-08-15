<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use App\Models\WhitelistedDomain;
use App\Services\FraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    private function mockSuccessfulReport(): void
    {
        $this->mock(FraudCheckService::class, function ($mock) {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => $phone);
            $mock->shouldReceive('getReport')
                ->andReturn([
                    'total_order' => 0,
                    'confirmed' => 0,
                    'frauds' => [],
                    'cancel' => 0,
                    'success_rate' => 'No order history found!',
                    'courier' => [],
                ]);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['fraud_check.plugin_free_checks_without_steadfast' => 10]);
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

        $this->mockSuccessfulReport();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://non-whitelisted.example.com'))
            ->postJson('/api/fraud-check', [
                'phone' => '01712345678',
            ]);

        $response->assertOk()
            ->assertJsonMissingPath('free_access');
    }

    public function test_fraud_check_allows_free_tier_without_whitelist_or_steadfast_credentials(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('non-whitelisted.example.com');

        $this->mockSuccessfulReport();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://non-whitelisted.example.com'))
            ->postJson('/api/fraud-check', [
                'phone' => '01712345678',
            ]);

        $response->assertOk()
            ->assertJsonPath('free_access.active', true)
            ->assertJsonPath('free_access.free_check_limit', 10)
            ->assertJsonPath('free_access.remaining_free_checks', 9);
    }

    public function test_fraud_check_alerts_after_free_quota_exhausted(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('non-whitelisted.example.com');
        config(['fraud_check.plugin_free_checks_without_steadfast' => 2]);

        $this->mockSuccessfulReport();

        $headers = $this->apiHeaders($plainToken, 'https://non-whitelisted.example.com');

        $this->withHeaders($headers)->postJson('/api/fraud-check', ['phone' => '01712345678'])->assertOk();
        $this->withHeaders($headers)->postJson('/api/fraud-check', ['phone' => '01712345679'])->assertOk();

        $response = $this->withHeaders($headers)
            ->postJson('/api/fraud-check', [
                'phone' => '01712345680',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('limited', true)
            ->assertJsonPath('code', 'steadfast_credentials_required')
            ->assertJsonPath('remaining_free_checks', 0);
    }

    public function test_fraud_check_batch_larger_than_remaining_free_quota_gets_clear_alert(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('non-whitelisted.example.com');
        config(['fraud_check.plugin_free_checks_without_steadfast' => 2]);

        $this->mockSuccessfulReport();

        $headers = $this->apiHeaders($plainToken, 'https://non-whitelisted.example.com');

        $this->withHeaders($headers)->postJson('/api/fraud-check', ['phone' => '01712345678'])->assertOk();

        $response = $this->withHeaders($headers)
            ->postJson('/api/fraud-check', [
                'data' => [
                    ['id' => 1, 'phone' => '01712345679'],
                    ['id' => 2, 'phone' => '01712345680'],
                ],
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('limited', true)
            ->assertJsonPath('reason', 'batch_too_large')
            ->assertJsonPath('remaining_free_checks', 1);
    }

    public function test_whitelisted_domain_stays_unlimited_without_steadfast_credentials(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('allowed.example.com');

        WhitelistedDomain::create([
            'domain' => 'allowed.example.com',
            'is_active' => true,
        ]);

        $this->mockSuccessfulReport();

        $headers = $this->apiHeaders($plainToken, 'https://allowed.example.com');

        for ($i = 0; $i < 12; $i++) {
            $this->withHeaders($headers)
                ->postJson('/api/fraud-check', ['phone' => '0171234567'.($i % 10)])
                ->assertOk()
                ->assertJsonMissingPath('free_access');
        }
    }
}
