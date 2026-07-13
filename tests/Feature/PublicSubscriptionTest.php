<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use App\Models\Website;
use App\Mail\SubscriptionInquiryAdminMail;
use App\Services\DomainNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'landing.admin_email' => 'admin@example.com',
            'landing.bkash_number' => '01770989591',
            'landing.rocket_number' => '01770989591',
            'landing.nagad_number' => '01770989591',
        ]);

        $this->mockDnsPass();
    }

    private function mockDnsPass(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $real = new DomainNormalizer();

            $mock->shouldReceive('normalize')
                ->andReturnUsing(fn (?string $input) => $real->normalize($input));
            $mock->shouldReceive('matches')
                ->andReturnUsing(fn (?string $left, ?string $right) => $real->matches($left, $right));
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) use ($real) {
                    $real->constrainMatchingDomain($query, $column, $domain);
                });
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
            $mock->shouldReceive('resolvesPublicly')->andReturn(true);
        });
    }

    private function mockDnsFail(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $real = new DomainNormalizer();

            $mock->shouldReceive('normalize')
                ->andReturnUsing(fn (?string $input) => $real->normalize($input));
            $mock->shouldReceive('matches')
                ->andReturnUsing(fn (?string $left, ?string $right) => $real->matches($left, $right));
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) use ($real) {
                    $real->constrainMatchingDomain($query, $column, $domain);
                });
            $mock->shouldReceive('hasDnsARecord')->andReturn(false);
            $mock->shouldReceive('resolvesPublicly')->andReturn(false);
        });
    }

    public function test_guest_can_submit_pricing_subscription_inquiry(): void
    {
        Mail::fake();

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

        Mail::assertSent(SubscriptionInquiryAdminMail::class, function (SubscriptionInquiryAdminMail $mail) {
            return $mail->hasTo('admin@example.com')
                && $mail->inquiry->domain === 'myshop.com'
                && $mail->inquiry->email === 'karim@example.com';
        });
    }

    public function test_paid_submit_is_rejected_when_no_payment_methods_configured(): void
    {
        config([
            'landing.bkash_number' => null,
            'landing.rocket_number' => null,
            'landing.nagad_number' => null,
        ]);

        $plan = $this->createPaidPlan();

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'nopay.com',
            'customer_name' => 'Karim',
            'email' => 'nopay@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01711111111',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN999',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors('transaction_method');
        $this->assertSame(0, SubscriptionInquiry::count());
    }

    public function test_paid_submit_rejects_unconfigured_transaction_method(): void
    {
        config([
            'landing.bkash_number' => '01770989591',
            'landing.rocket_number' => null,
            'landing.nagad_number' => null,
        ]);

        $plan = $this->createPaidPlan();

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'onlybkash.com',
            'customer_name' => 'Karim',
            'email' => 'onlybkash@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01711111111',
            'address' => 'Dhaka',
            'transaction_method' => 'Nagad',
            'transaction_id' => 'TXN888',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors('transaction_method');
        $this->assertSame(0, SubscriptionInquiry::count());
    }

    public function test_submit_rejects_domain_without_dns(): void
    {
        $this->mockDnsFail();

        $plan = $this->createPaidPlan();

        $response = $this->from('/pricing')->post(route('pricing.subscribe'), [
            'package_hub_id' => $plan->id,
            'website_url' => 'no-dns-shop.test',
            'customer_name' => 'Karim',
            'email' => 'nodns@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01711111111',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN777',
            'account_number' => '01711111111',
        ]);

        $response->assertRedirect('/pricing');
        $response->assertSessionHasErrors('website_url');
        $this->assertSame(0, SubscriptionInquiry::count());
    }

    public function test_realtime_validate_rejects_domain_without_dns(): void
    {
        $this->mockDnsFail();

        $response = $this->postJson(route('pricing.subscribe.validate'), [
            'website_url' => 'no-dns-shop.test',
            'email' => 'buyer@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01711111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors.website_url', fn ($message) => is_string($message) && str_contains($message, 'DNS'));
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

    public function test_realtime_validate_endpoint_flags_taken_merchant_domain(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'owner3@example.com',
            'phone' => '01700000003',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'live-shop.com',
            'title' => 'live-shop.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->postJson(route('pricing.subscribe.validate'), [
            'website_url' => 'live-shop.com',
            'email' => 'buyer@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01711111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors.website_url', fn ($message) => is_string($message) && $message !== '');
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
        $response->assertSessionHas('subscription_submitted', function (array $payload) {
            return ($payload['is_free_trial'] ?? false) === true
                && ($payload['currency'] ?? null) === 'BDT'
                && (float) ($payload['value'] ?? -1) === 0.0;
        });

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
