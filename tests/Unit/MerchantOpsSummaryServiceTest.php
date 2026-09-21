<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\MerchantOpsSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantOpsSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_flags_expired_subscription_with_domain(): void
    {
        $user = $this->createMerchant();

        Website::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Starter',
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

        $users = app(MerchantOpsSummaryService::class)->appendToUsers(collect([$user]));
        $attention = $users->first()->attention;

        $this->assertSame(['shop.example.com'], $users->first()->domains);
        $this->assertSame('expired', $attention['code']);
        $this->assertSame('danger', $attention['severity']);
        $this->assertSame('shop.example.com', $attention['domain']);
    }

    public function test_admins_have_no_attention_row(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $users = app(MerchantOpsSummaryService::class)->appendToUsers(collect([$admin]));

        $this->assertSame([], $users->first()->domains);
        $this->assertNull($users->first()->attention);
    }

    public function test_flags_merchant_with_no_website(): void
    {
        $user = $this->createMerchant();

        $users = app(MerchantOpsSummaryService::class)->appendToUsers(collect([$user]));

        $this->assertSame('no_website', $users->first()->attention['code']);
    }

    public function test_does_not_flag_healthy_subscription(): void
    {
        $user = $this->createMerchant();

        Website::create([
            'user_id' => $user->id,
            'domain' => 'ok.example.com',
            'status' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Starter',
            'domain' => 'ok.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        AccessToken::unguarded(function () use ($user, $package) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'healthy-token'),
                'domain' => 'ok.example.com',
                'user_package_id' => $package->id,
                'status' => true,
            ]);
        });

        $users = app(MerchantOpsSummaryService::class)->appendToUsers(collect([$user]));

        $this->assertNull($users->first()->attention);
        $this->assertSame(['ok.example.com'], $users->first()->domains);
    }
}
