<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    public function test_admin_can_view_orders_page(): void
    {
        $admin = $this->adminUser();

        PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        $this->post(route('pricing.subscribe'), [
            'package_hub_id' => 1,
            'website_url' => 'myshop.com',
            'email' => 'buyer@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN999',
            'account_number' => '01711111111',
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders', 1)
            ->where('orders.0.email', 'buyer@example.com'));
    }

    public function test_admin_can_search_orders(): void
    {
        $admin = $this->adminUser();

        $plan = PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);

        \App\Models\SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'myshop.com',
            'email' => 'buyer@example.com',
            'contact_number' => '01711111111',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 999,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN999',
            'account_number' => '01711111111',
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->actingAs($admin)->get(route('orders.index', [
            'status' => 'all',
            'search' => 'TXN999',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders', 1)
            ->where('search', 'TXN999'));
    }
}
