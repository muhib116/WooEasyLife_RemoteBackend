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
            ->assertJsonPath('active_package.remaining_order', 75)
            ->assertJsonPath('active_package.total_order_can_handle', 100)
            ->assertJsonPath('active_package.title', 'Standard')
            ->assertJsonStructure([
                'id',
                'name',
                'sms_balance',
                'billing',
                'active_package' => [
                    'id',
                    'package_hub_id',
                    'plan_type',
                    'title',
                    'expires_at',
                    'remaining_order',
                    'total_order_can_handle',
                    'features',
                ],
            ]);

        $this->assertIsArray($response->json('billing.payment_methods'));
        $notice = $response->json('notice');
        $this->assertTrue($notice === null || is_array($notice));
    }

    public function test_get_user_includes_active_package_with_features(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $plan = PackageHub::create([
            'title' => 'Pro Monthly',
            'description' => 'Pro plan',
            'per_order_rate' => 0,
            'package_price' => 999,
            'order_rate_token' => 500,
            'package_duration' => '1_month',
            'is_active' => true,
            'index' => 2,
            'features' => [
                'fraud_customer_checker' => true,
                'bulk_sms' => false,
            ],
        ]);

        UserPackage::create([
            'title' => 'Pro Monthly',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'total_order_can_handle' => 500,
            'remaining_order' => 400,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'features' => [
                'fraud_customer_checker' => true,
                'bulk_sms' => false,
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('active_package.plan_type', 'catalog')
            ->assertJsonPath('active_package.package_hub_id', $plan->id)
            ->assertJsonPath('active_package.features.fraud_customer_checker', true)
            ->assertJsonPath('active_package.features.bulk_sms', false);
    }

    public function test_get_user_returns_null_active_package_when_no_package(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('active_package', null);
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

    public function test_catalog_subscription_works_with_package_order_use(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Starter – 1 Month',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'plan_type' => 'catalog',
            'order_rate_token' => 1000,
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 1000,
            'total_order_handled' => 0,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package-order-use', [
                'order_count' => 3,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.remaining_order', 997);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user')
            ->assertOk()
            ->assertJsonPath('remaining_order', 997)
            ->assertJsonPath('billing.plan_type', 'catalog');
    }
}
