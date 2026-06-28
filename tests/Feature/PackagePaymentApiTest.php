<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\PackagePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @group plugin-api
 */
class PackagePaymentApiTest extends TestCase
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

    private function createPlan(): PackageHub
    {
        return PackageHub::create([
            'title' => 'Standard',
            'description' => 'Standard plan',
            'per_order_rate' => 1,
            'is_active' => true,
            'index' => 1,
        ]);
    }

    public function test_get_user_includes_additive_billing_fields(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 10,
            'remaining_order' => 5,
            'total_order_handled' => 5,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('remaining_order', 5)
            ->assertJsonPath('billing.subscription_status', 'active')
            ->assertJsonPath('billing.remaining_order', 5)
            ->assertJsonPath('billing.has_pending_payment', false)
            ->assertJsonStructure([
                'billing' => [
                    'subscription_status',
                    'remaining_order',
                    'expires_at',
                    'pending_payment_count',
                    'has_pending_payment',
                ],
                'license' => ['expires_at', 'status'],
            ]);
    }

    public function test_plugin_can_list_plans_and_submit_payment_request(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        $plansResponse = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/plans');

        $plansResponse->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        $submitResponse = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 100,
                'total_amount' => 100,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN123',
                'account_number' => '01700000000',
            ]);

        $submitResponse->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'status' => 'pending',
            'order_limit' => 100,
        ]);
    }

    public function test_expired_token_can_still_access_package_renewal_routes(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->update([
                'expires_at' => now()->subDay(),
            ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Expired');

        $plansResponse = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/plans');

        $plansResponse->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data');

        $submitResponse = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 100,
                'total_amount' => 100,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-EXPIRED',
                'account_number' => '01700000000',
            ]);

        $submitResponse->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $user->id,
            'transaction_id' => 'TXN-EXPIRED',
            'status' => 'pending',
        ]);
    }

    public function test_approving_payment_assigns_subscription(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        $paymentRequest = PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 50,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN999',
            'account_number' => '01700000000',
            'status' => 'pending',
        ]);

        app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'remaining_order' => 50,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('package_payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'approved',
        ]);
    }

    public function test_approving_payment_tops_up_existing_subscription(): void
    {
        [$user] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 20,
            'remaining_order' => 5,
            'total_order_handled' => 15,
            'per_order_rate' => 1,
            'total_cost' => 20,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $paymentRequest = PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 30,
            'total_amount' => 30,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN888',
            'account_number' => '01700000000',
            'status' => 'pending',
        ]);

        app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'remaining_order' => 35,
            'total_order_can_handle' => 50,
        ]);
    }
}
