<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\SubscriptionInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
