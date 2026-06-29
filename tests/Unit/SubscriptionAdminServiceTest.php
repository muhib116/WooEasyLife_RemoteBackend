<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_catalog_renewal_merges_payment_attributes(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Starter – 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => $plan->title,
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'order_rate_token' => 1000,
            'total_order_can_handle' => 1000,
            'remaining_order' => 50,
            'total_order_handled' => 950,
            'total_cost' => 999,
            'per_order_rate' => 0,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $renewed = app(SubscriptionAdminService::class)->applyCatalogRenewal($userPackage, $plan, [
            'total_cost' => 1998,
            'transaction_charge' => 10,
            'transaction_method' => 'Bkash',
        ]);

        $this->assertSame(1000, $renewed->remaining_order);
        $this->assertSame(1998.0, (float) $renewed->total_cost);
        $this->assertSame(10.0, (float) $renewed->transaction_charge);
        $this->assertSame('Bkash', $renewed->transaction_method);
        $this->assertTrue($renewed->expires_at->greaterThan(now()));
    }
}
