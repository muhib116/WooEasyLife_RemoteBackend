<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionAdminServiceDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_plan_rejects_domain_owned_by_other_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000050',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'phone' => '01700000051',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        \App\Models\Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $legacyPlan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $premiumPlan = PackageHub::create([
            'title' => 'Premium',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $existing = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'other.example.com',
            'user_id' => $intruder->id,
            'package_hub_id' => $legacyPlan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(SubscriptionAdminService::class)->changePlan($intruder, $existing, $premiumPlan, [
            'domain' => 'shop.example.com',
            'limit' => 100,
            'transaction_method' => 'Cash',
        ]);
    }
}
