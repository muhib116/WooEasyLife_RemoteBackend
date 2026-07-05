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

    private function createLegacyPlan(): PackageHub
    {
        return PackageHub::create([
            'title' => 'Standard',
            'description' => 'Standard plan',
            'per_order_rate' => 1,
            'is_active' => true,
            'index' => 1,
        ]);
    }

    private function createCatalogPlan(): PackageHub
    {
        return PackageHub::create([
            'title' => 'Standard',
            'description' => 'Standard plan',
            'per_order_rate' => 0,
            'package_price' => 100,
            'package_duration' => '1_month',
            'order_rate_token' => 100,
            'is_active' => true,
            'index' => 1,
            'features' => [
                'fraud_customer_checker' => true,
                'sms_management' => true,
            ],
        ]);
    }

    private function createPlan(): PackageHub
    {
        return $this->createLegacyPlan();
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
            ->assertJsonPath('billing.total_order_can_handle', 10)
            ->assertJsonPath('billing.total_order_handled', 5)
            ->assertJsonPath('billing.package_hub_id', $plan->id)
            ->assertJsonPath('billing.can_renew_current_plan', false)
            ->assertJsonPath('billing.can_upgrade_plan', true)
            ->assertJsonPath('billing.has_pending_payment', false)
            ->assertJsonPath('billing.can_submit_payment', true)
            ->assertJsonStructure([
                'billing' => [
                    'subscription_status',
                    'remaining_order',
                    'expires_at',
                    'pending_payment_count',
                    'has_pending_payment',
                    'pending_payments',
                    'package_hub_id',
                    'can_renew_current_plan',
                    'can_upgrade_plan',
                    'can_submit_payment',
                    'can_subscribe_plan',
                    'current_plan_package_price',
                    'current_plan_index',
                    'current_plan_package_duration',
                ],
                'license' => ['expires_at', 'status'],
            ]);
    }

    public function test_plugin_can_list_plans_and_submit_payment_request(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createCatalogPlan();

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

    public function test_plugin_plans_include_dynamic_bangla_display_payload(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->createCatalogPlan();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/plans');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'title',
                        'duration_label',
                        'price_label',
                        'token_label',
                        'website_label',
                        'features_heading',
                        'top_features',
                        'all_features',
                        'catalog_features',
                        'feature_lines',
                        'summary_lines',
                        'enabled_feature_count',
                        'more_features_count',
                    ],
                ],
            ])
            ->assertJsonPath('data.0.duration_label', 'মাসিক প্ল্যান')
            ->assertJsonPath('data.0.price_label', '৳100')
            ->assertJsonPath('data.0.features_heading', 'প্ল্যান ফিচার')
            ->assertJsonPath('data.0.top_features.0.label', 'ফ্রড কাস্টমার চেকার');
    }

    public function test_expired_token_can_still_access_package_renewal_routes(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createCatalogPlan();

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

    public function test_expired_token_can_load_package_billing_snapshot(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        AccessToken::query()
            ->where('tokenable_id', $user->id)
            ->update([
                'expires_at' => now()->subDay(),
            ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Expired');

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/billing');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.package_hub_id', $plan->id)
            ->assertJsonPath('data.subscription_status', 'expired')
            ->assertJsonPath('data.can_renew_current_plan', true)
            ->assertJsonStructure([
                'data' => [
                    'alerts',
                    'payment_methods',
                ],
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

    public function test_approving_payment_upgrades_legacy_plan_to_different_hub(): void
    {
        [$user] = $this->createMerchantWithToken();
        $standard = $this->createPlan();
        $premium = PackageHub::create([
            'title' => 'Premium',
            'description' => 'Premium plan',
            'per_order_rate' => 2,
            'is_active' => true,
            'index' => 2,
        ]);

        $existing = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $standard->id,
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
            'package_hub_id' => $premium->id,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-UPGRADE',
            'account_number' => '01700000000',
            'status' => 'pending',
            'payment_intent' => 'upgrade',
        ]);

        $upgraded = app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertFalse((bool) $existing->fresh()->is_active);
        $this->assertSame($premium->id, $upgraded->package_hub_id);
        $this->assertSame('Premium', $upgraded->title);
        $this->assertSame(50, $upgraded->remaining_order);
        $this->assertSame(50, $upgraded->total_order_can_handle);
    }

    public function test_payment_request_stores_resolved_intent_without_enforcement(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => false]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 50,
                'total_amount' => 50,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-RENEW-LEGACY',
                'account_number' => '01700000000',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $user->id,
            'transaction_id' => 'TXN-RENEW-LEGACY',
            'payment_intent' => 'renew',
            'status' => 'pending',
        ]);
    }

    public function test_payment_request_rejects_renew_while_active_when_enforced(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 50,
                'total_amount' => 50,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-BLOCKED',
                'account_number' => '01700000000',
                'intent' => 'renew',
            ]);

        $response->assertStatus(422);
    }

    public function test_payment_request_allows_upgrade_while_active_when_enforced(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $standard = $this->createPlan();
        $premium = PackageHub::create([
            'title' => 'Premium',
            'description' => 'Premium plan',
            'per_order_rate' => 2,
            'is_active' => true,
            'index' => 2,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $standard->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $premium->id,
                'order_limit' => 50,
                'total_amount' => 100,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-UPGRADE-API',
                'account_number' => '01700000000',
                'intent' => 'upgrade',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('package_payment_requests', [
            'transaction_id' => 'TXN-UPGRADE-API',
            'payment_intent' => 'upgrade',
            'package_hub_id' => $premium->id,
        ]);
    }

    public function test_plugin_blocks_second_payment_while_one_is_pending(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-PENDING',
            'account_number' => '01700000000',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 50,
                'total_amount' => 50,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-BLOCKED-PENDING',
                'account_number' => '01700000000',
            ]);

        $response->assertStatus(422);
    }

    public function test_billing_includes_pending_payment_details_and_blocks_capabilities(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-PENDING-2',
            'account_number' => '01700000000',
            'status' => 'pending',
            'payment_intent' => 'renew',
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/billing');

        $response->assertOk()
            ->assertJsonPath('data.has_pending_payment', true)
            ->assertJsonPath('data.can_submit_payment', false)
            ->assertJsonPath('data.can_upgrade_plan', false)
            ->assertJsonPath('data.pending_payments.0.transaction_id', 'TXN-PENDING-2')
            ->assertJsonPath('data.pending_payments.0.plan_title', 'Standard');
    }

    public function test_payment_request_includes_submission_guide(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 100,
                'total_amount' => 100,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-GUIDE',
                'account_number' => '01700000000',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.submission.status', 'pending_review')
            ->assertJsonPath('data.submission.title', 'পেমেন্ট সফলভাবে জমা হয়েছে')
            ->assertJsonPath('data.submission.steps.0.status', 'completed')
            ->assertJsonPath('data.submission.steps.0.label', 'পেমেন্ট জমা হয়েছে')
            ->assertJsonPath('data.transaction_id', 'TXN-GUIDE');
    }

    public function test_quote_returns_upgrade_pricing_for_active_plan(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $standard = $this->createPlan();
        $premium = PackageHub::create([
            'title' => 'Premium',
            'description' => 'Premium plan',
            'per_order_rate' => 2,
            'is_active' => true,
            'index' => 2,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $standard->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/quote?' . http_build_query([
                'package_hub_id' => $premium->id,
                'order_limit' => 50,
                'intent' => 'upgrade',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.intent', 'upgrade')
            ->assertJsonPath('data.total_amount', 100)
            ->assertJsonPath('data.order_limit', 50);
    }

    public function test_quote_returns_downgrade_intent_for_lower_catalog_plan(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $starter = PackageHub::create([
            'title' => 'Starter',
            'description' => 'Starter plan',
            'per_order_rate' => 0,
            'package_price' => 999,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 1,
        ]);
        $growth = PackageHub::create([
            'title' => 'Growth',
            'description' => 'Growth plan',
            'per_order_rate' => 0,
            'package_price' => 2499,
            'package_duration' => '1_month',
            'order_rate_token' => 3000,
            'is_active' => true,
            'index' => 2,
        ]);

        UserPackage::create([
            'title' => 'Growth',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $growth->id,
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'total_order_can_handle' => 3000,
            'remaining_order' => 2500,
            'total_order_handled' => 500,
            'per_order_rate' => 0,
            'total_cost' => 2499,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/quote?' . http_build_query([
                'package_hub_id' => $starter->id,
                'intent' => 'downgrade',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.intent', 'downgrade')
            ->assertJsonPath('data.total_amount', 999);
    }

    public function test_payment_request_allows_downgrade_while_active_when_enforced(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $starter = PackageHub::create([
            'title' => 'Starter',
            'description' => 'Starter plan',
            'per_order_rate' => 0,
            'package_price' => 999,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 1,
        ]);
        $growth = PackageHub::create([
            'title' => 'Growth',
            'description' => 'Growth plan',
            'per_order_rate' => 0,
            'package_price' => 2499,
            'package_duration' => '1_month',
            'order_rate_token' => 3000,
            'is_active' => true,
            'index' => 2,
        ]);

        UserPackage::create([
            'title' => 'Growth',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $growth->id,
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'total_order_can_handle' => 3000,
            'remaining_order' => 2500,
            'total_order_handled' => 500,
            'per_order_rate' => 0,
            'total_cost' => 2499,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $starter->id,
                'total_amount' => 999,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-DOWNGRADE-API',
                'account_number' => '01700000000',
                'intent' => 'downgrade',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('package_payment_requests', [
            'transaction_id' => 'TXN-DOWNGRADE-API',
            'payment_intent' => 'downgrade',
            'package_hub_id' => $starter->id,
        ]);
    }

    public function test_billing_reports_domain_trial_used_after_prior_trial(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $trial = PackageHub::create([
            'title' => 'Free Trial',
            'description' => 'Trial plan',
            'per_order_rate' => 0,
            'package_price' => 0,
            'package_duration' => 'free_trial',
            'trial_days' => 14,
            'order_rate_token' => 100,
            'is_active' => true,
            'index' => 0,
        ]);
        $starter = PackageHub::create([
            'title' => 'Starter',
            'description' => 'Starter plan',
            'per_order_rate' => 0,
            'package_price' => 999,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 1,
        ]);

        UserPackage::create([
            'title' => 'Free Trial',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $trial->id,
            'plan_type' => 'catalog',
            'package_duration' => 'free_trial',
            'total_order_can_handle' => 100,
            'remaining_order' => 0,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 0,
            'transaction_charge' => 0,
            'is_active' => false,
        ]);

        UserPackage::create([
            'title' => 'Starter',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $starter->id,
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 800,
            'total_order_handled' => 200,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/package/billing');

        $response->assertOk()
            ->assertJsonPath('data.domain_trial_used', true);
    }

    public function test_payment_request_rejects_free_trial_when_domain_already_used_trial(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $trial = PackageHub::create([
            'title' => 'Free Trial',
            'description' => 'Trial plan',
            'per_order_rate' => 0,
            'package_price' => 0,
            'package_duration' => 'free_trial',
            'trial_days' => 14,
            'order_rate_token' => 100,
            'is_active' => true,
            'index' => 0,
        ]);
        $starter = PackageHub::create([
            'title' => 'Starter',
            'description' => 'Starter plan',
            'per_order_rate' => 0,
            'package_price' => 999,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'is_active' => true,
            'index' => 1,
        ]);

        UserPackage::create([
            'title' => 'Free Trial',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $trial->id,
            'plan_type' => 'catalog',
            'package_duration' => 'free_trial',
            'total_order_can_handle' => 100,
            'remaining_order' => 0,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 0,
            'transaction_charge' => 0,
            'is_active' => false,
        ]);

        UserPackage::create([
            'title' => 'Starter',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $starter->id,
            'plan_type' => 'catalog',
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 800,
            'total_order_handled' => 200,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        config(['subscription_payments.enforce_intent_rules' => true]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $trial->id,
                'total_amount' => 0,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-TRIAL-BLOCKED',
                'account_number' => '01700000000',
                'intent' => 'downgrade',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['package_hub_id']);
    }

    public function test_portal_can_still_submit_while_plugin_payment_pending(): void
    {
        [$user] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-PORTAL-OK',
            'account_number' => '01700000000',
            'status' => 'pending',
        ]);

        $request = app(PackagePaymentService::class)->createRequest($user, [
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 50,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-ADMIN-MANUAL',
            'account_number' => '01700000000',
        ]);

        $this->assertSame('pending', $request->status);
    }

    public function test_plugin_rejects_mismatched_payment_amount(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        config([
            'subscription_payments.validate_plugin_amounts' => true,
            'subscription_payments.enforce_intent_rules' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 100,
                'total_amount' => 50,
                'transaction_charge' => 0,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-BAD-AMOUNT',
                'account_number' => '01700000000',
                'intent' => 'subscribe',
            ]);

        $response->assertStatus(422);
    }

    public function test_plugin_accepts_amount_with_transaction_fee_included(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $plan = $this->createPlan();

        config([
            'subscription_payments.validate_plugin_amounts' => true,
            'subscription_payments.enforce_intent_rules' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/package/payment-request', [
                'package_hub_id' => $plan->id,
                'order_limit' => 100,
                'total_amount' => 101.8,
                'transaction_charge' => 1.8,
                'transaction_method' => 'Bkash',
                'transaction_id' => 'TXN-FEE-OK',
                'account_number' => '01700000000',
                'intent' => 'subscribe',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true);
    }
}
