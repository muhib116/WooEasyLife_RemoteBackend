<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\PackagePaymentService;
use App\Services\PlanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogPlanAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    private function createCatalogPlan(array $overrides = []): PackageHub
    {
        return PackageHub::create(array_merge([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'index' => 1,
        ], $overrides));
    }

    public function test_catalog_assign_sets_tokens_price_and_expiry(): void
    {
        $user = $this->createMerchant();
        $plan = $this->createCatalogPlan();

        $userPackage = app(PlanAssignmentService::class)->assign($user, $plan, [
            'domain' => 'shop.example.com',
            'transaction_method' => 'Cash',
        ]);

        $this->assertSame('catalog', $userPackage->plan_type);
        $this->assertSame(1000, $userPackage->remaining_order);
        $this->assertSame(1000, $userPackage->total_order_can_handle);
        $this->assertSame(999.0, (float) $userPackage->total_cost);
        $this->assertSame(0.0, (float) $userPackage->per_order_rate);
        $this->assertNotNull($userPackage->expires_at);
        $this->assertTrue($userPackage->expires_at->greaterThan(now()));
    }

    public function test_legacy_assign_is_unchanged(): void
    {
        $user = $this->createMerchant();
        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $userPackage = app(PlanAssignmentService::class)->assign($user, $plan, [
            'domain' => 'shop.example.com',
            'limit' => 300,
            'transaction_method' => 'Cash',
        ]);

        $this->assertSame('legacy', $userPackage->plan_type);
        $this->assertSame(300, $userPackage->remaining_order);
        $this->assertSame(600.0, (float) $userPackage->total_cost);
        $this->assertNull($userPackage->expires_at);
    }

    public function test_catalog_payment_request_uses_fixed_price(): void
    {
        $user = $this->createMerchant();
        $plan = $this->createCatalogPlan();

        $request = app(PackagePaymentService::class)->createRequest($user, [
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'total_amount' => 999,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN1',
            'account_number' => '01700000000',
        ]);

        $this->assertSame(1000, $request->order_limit);
        $this->assertSame(999.0, (float) $request->total_amount);
    }

    public function test_approving_catalog_payment_renews_existing_catalog_subscription(): void
    {
        $user = $this->createMerchant();
        $plan = $this->createCatalogPlan();

        $existing = UserPackage::create([
            'title' => $plan->title,
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 1000,
            'package_duration' => '1_month',
            'total_order_can_handle' => 1000,
            'remaining_order' => 50,
            'total_order_handled' => 950,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $paymentRequest = app(PackagePaymentService::class)->createRequest($user, [
            'package_hub_id' => $plan->id,
            'domain' => 'shop.example.com',
            'total_amount' => 999,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN2',
            'account_number' => '01700000000',
        ]);

        $updated = app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertSame($existing->id, $updated->id);
        $this->assertSame(1000, $updated->remaining_order);
        $this->assertSame(0, $updated->total_order_handled);
        $this->assertTrue($updated->expires_at->greaterThan(now()));
    }

    public function test_approving_catalog_payment_migrates_legacy_subscription(): void
    {
        $user = $this->createMerchant();
        $legacy = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);
        $catalog = $this->createCatalogPlan();

        $existing = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $legacy->id,
            'plan_type' => 'legacy',
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $paymentRequest = app(PackagePaymentService::class)->createRequest($user, [
            'package_hub_id' => $catalog->id,
            'domain' => 'shop.example.com',
            'total_amount' => 999,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN3',
            'account_number' => '01700000000',
        ]);

        $approved = app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertFalse((bool) $existing->fresh()->is_active);
        $this->assertSame('catalog', $approved->plan_type);
        $this->assertSame($catalog->id, $approved->package_hub_id);
        $this->assertSame(1000, $approved->remaining_order);
        $this->assertNotNull($approved->expires_at);
        $this->assertDatabaseHas('package_payment_requests', [
            'id' => $paymentRequest->id,
            'status' => 'approved',
            'user_package_id' => $approved->id,
        ]);
    }

    public function test_approving_catalog_payment_upgrades_to_different_catalog_hub(): void
    {
        $user = $this->createMerchant();
        $basic = $this->createCatalogPlan([
            'title' => 'Basic – 1 Month',
            'order_rate_token' => 600,
            'package_price' => 499,
        ]);
        $pro = $this->createCatalogPlan([
            'title' => 'Pro – 1 Month',
            'order_rate_token' => 2000,
            'package_price' => 1999,
            'index' => 2,
        ]);

        $existing = UserPackage::create([
            'title' => $basic->title,
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $basic->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 600,
            'package_duration' => '1_month',
            'total_order_can_handle' => 600,
            'remaining_order' => 100,
            'total_order_handled' => 500,
            'per_order_rate' => 0,
            'total_cost' => 499,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addDays(10),
        ]);

        $paymentRequest = app(PackagePaymentService::class)->createRequest($user, [
            'package_hub_id' => $pro->id,
            'domain' => 'shop.example.com',
            'total_amount' => 1999,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-CAT-UP',
            'account_number' => '01700000000',
            'intent' => 'upgrade',
        ]);

        $updated = app(PackagePaymentService::class)->approve($paymentRequest);

        $this->assertSame($existing->id, $updated->id);
        $this->assertSame($pro->id, $updated->package_hub_id);
        $this->assertSame('Pro – 1 Month', $updated->title);
        $this->assertSame(2000, $updated->remaining_order);
        $this->assertSame(0, $updated->total_order_handled);
        $this->assertTrue($updated->expires_at->greaterThan(now()));
    }
}
