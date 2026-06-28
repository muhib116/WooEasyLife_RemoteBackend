<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_expiry_disables_expired_tokens(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $token = AccessToken::unguarded(fn () => AccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Expired Token',
            'token' => hash('sha256', 'expired-token'),
            'domain' => 'shop.example.com',
            'status' => true,
            'expires_at' => now()->subDay(),
        ]));

        $this->artisan('subscriptions:apply-expiry')->assertSuccessful();

        $this->assertFalse((bool) $token->fresh()->status);
    }

    public function test_apply_expiry_deactivates_expired_plans(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:apply-expiry')->assertSuccessful();

        $package->refresh();
        $this->assertFalse((bool) $package->is_active);
    }

    public function test_apply_expiry_deactivates_exhausted_plans(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 0,
            'total_order_handled' => 100,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->artisan('subscriptions:apply-expiry')->assertSuccessful();

        $this->assertFalse((bool) $package->fresh()->is_active);
    }
}
