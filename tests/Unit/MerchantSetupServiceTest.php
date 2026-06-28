<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\MerchantSetupService;
use App\Services\WebsiteAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantSetupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_ready_for_plugin_requires_plugin_connection(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
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
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $service = app(MerchantSetupService::class);
        $configuredOnly = $service->progress($user);

        $this->assertTrue($configuredOnly['configured_for_plugin']);
        $this->assertFalse($configuredOnly['ready_for_plugin']);

        AccessToken::query()->where('tokenable_id', $user->id)->update([
            'last_used_at' => now(),
        ]);

        $connected = $service->progress($user->fresh());

        $this->assertTrue($connected['ready_for_plugin']);
    }
}
