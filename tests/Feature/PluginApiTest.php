<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @group plugin-api
 */
class PluginApiTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchantWithToken(
        string $domain = 'shop.example.com',
        ?string $plainToken = null
    ): array {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = $plainToken ?? 'test-token-' . bin2hex(random_bytes(16));

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

    public function test_get_user_returns_profile_and_balances(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('remaining_order', 75)
            ->assertJsonStructure(['id', 'name', 'sms_balance', 'notice']);
    }

    public function test_get_user_rejects_missing_origin(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/get-user');

        $response->assertStatus(400);
    }

    public function test_get_user_rejects_domain_mismatch(): void
    {
        [, $plainToken] = $this->createMerchantWithToken('shop.example.com');

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://other.example.com'))
            ->getJson('/api/get-user');

        $response->assertUnauthorized();
    }

    public function test_validate_token_returns_true_for_valid_credentials(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/validate-token');

        $response->assertOk();
        $this->assertTrue((bool) $response->json());
    }

    public function test_package_order_use_deducts_remaining_orders(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 10,
            'remaining_order' => 10,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.remaining_order', 8);

        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'remaining_order' => 8,
            'total_order_handled' => 2,
        ]);
    }

    public function test_package_order_use_returns_limit_flag_when_no_package(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 1,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('is_order_limit_over', true);
    }

    public function test_package_order_use_matches_plan_by_normalized_domain(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken('shop.example.com');

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 10,
            'remaining_order' => 10,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.remaining_order', 9);
    }

    public function test_disabled_token_is_rejected(): void
    {
        [$user] = $this->createMerchantWithToken();
        $plainToken = 'disabled-token-' . bin2hex(random_bytes(8));

        AccessToken::unguarded(function () use ($user, $plainToken) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Disabled',
                'token' => hash('sha256', $plainToken),
                'domain' => 'shop.example.com',
                'status' => false,
            ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertUnauthorized();
    }

    public function test_package_order_use_blocks_expired_subscription(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 10,
            'remaining_order' => 10,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 1,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('is_order_limit_over', true)
            ->assertJsonPath('message', 'Your subscription plan has expired.');
    }

    public function test_package_order_use_blocks_when_order_count_exceeds_remaining(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 10,
            'remaining_order' => 2,
            'total_order_handled' => 8,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 5,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('is_order_limit_over', true)
            ->assertJsonPath('message', 'Order count exceeds remaining quota');
    }
}
