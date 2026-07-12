<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_pricing_subscription_inquiry(): void
    {
        $plan = PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $response = $this->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'https://myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka, Bangladesh',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN123456',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');

        $this->assertDatabaseHas('subscription_inquiries', [
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'email' => 'karim@example.com',
            'whatsapp_number' => '01770989591',
            'status' => 'pending',
        ]);

        $this->assertSame(1, SubscriptionInquiry::count());
    }

    public function test_guest_cannot_subscribe_domain_owned_by_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $plan = $this->createPaidPlan();
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'owner@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'taken-shop.com',
            'title' => 'taken-shop.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'taken-shop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN111',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors(['website_url', 'subscription']);
        $this->assertSame(0, SubscriptionInquiry::count());
    }

    public function test_owner_merchant_can_subscribe_own_domain(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $plan = $this->createPaidPlan();
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'owner2@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'my-owned.com',
            'title' => 'my-owned.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($merchant)->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'my-owned.com',
            'customer_name' => 'Merchant',
            'email' => 'owner2@example.com',
            'contact_number' => '01700000002',
            'whatsapp_number' => '01700000002',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN222',
            'account_number' => '01700000002',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');
        $this->assertSame(1, SubscriptionInquiry::count());
    }

    public function test_duplicate_is_blocked_when_domain_and_email_match(): void
    {
        $plan = $this->createPaidPlan();

        SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'myshop.com',
            'customer_name' => 'Karim Again',
            'email' => 'karim@example.com',
            'contact_number' => '01722222222',
            'whatsapp_number' => '01733333333',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN999',
            'account_number' => '01722222222',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors('subscription');
        $this->assertSame(1, SubscriptionInquiry::count());
    }

    public function test_duplicate_is_blocked_when_domain_and_phone_match(): void
    {
        $plan = $this->createPaidPlan();

        SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'myshop.com',
            'customer_name' => 'Other Person',
            'email' => 'other@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01744444444',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN888',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors('subscription');
        $this->assertSame(1, SubscriptionInquiry::count());
    }

    public function test_same_domain_with_different_email_and_phone_is_allowed(): void
    {
        $plan = $this->createPaidPlan();

        SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'myshop.com',
            'customer_name' => 'Another Buyer',
            'email' => 'another@example.com',
            'contact_number' => '01722222222',
            'whatsapp_number' => '01733333333',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN777',
            'account_number' => '01722222222',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');
        $this->assertSame(2, SubscriptionInquiry::count());
    }

    public function test_same_email_on_different_domain_is_allowed(): void
    {
        $plan = $this->createPaidPlan();

        SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'othershop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN666',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');
        $this->assertSame(2, SubscriptionInquiry::count());
    }

    public function test_rejected_inquiry_allows_new_purchase(): void
    {
        $plan = $this->createPaidPlan();

        SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'rejected',
            'source' => 'landing_pricing',
        ]);

        $response = $this->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN555',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');
        $this->assertSame(2, SubscriptionInquiry::count());
    }

    public function test_landing_page_shows_pending_inquiry_from_session(): void
    {
        $plan = $this->createPaidPlan();

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'customer_name' => 'Karim',
            'email' => 'karim@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->withSession([
            \App\Services\PublicSubscriptionService::SESSION_PENDING_INQUIRY_KEY => $inquiry->id,
        ])->get('/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Welcome3')
            ->where('pendingSubscriptionInquiry.id', $inquiry->id)
            ->where('pendingSubscriptionInquiry.domain', 'myshop.com'));
    }

    public function test_free_trial_inquiry_does_not_require_transaction_id(): void
    {
        $plan = PackageHub::create([
            'title' => 'Free Trial',
            'per_order_rate' => 0,
            'package_duration' => 'free_trial',
            'order_rate_token' => 100,
            'package_price' => 0,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $response = $this->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'trialshop.com',
            'customer_name' => 'Trial User',
            'email' => 'trial@example.com',
            'contact_number' => '01722222222',
            'whatsapp_number' => '01770989591',
            'address' => 'Chittagong',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('subscription_submitted');

        $this->assertDatabaseHas('subscription_inquiries', [
            'package_hub_id' => $plan->id,
            'domain' => 'trialshop.com',
            'total_amount' => 0,
        ]);
    }

    private function createPaidPlan(): PackageHub
    {
        return PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);
    }
}
