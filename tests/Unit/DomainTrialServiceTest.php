<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainTrialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainTrialServiceTest extends TestCase
{
    use RefreshDatabase;

    private DomainTrialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DomainTrialService::class);
    }

    public function test_detects_free_trial_from_package_duration_snapshot(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'trial@example.com',
            'phone' => '01700000020',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Free Trial',
            'per_order_rate' => 0,
            'package_price' => 0,
            'package_duration' => 'free_trial',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Free Trial',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'package_duration' => 'free_trial',
            'plan_type' => 'catalog',
            'total_order_can_handle' => 100,
            'remaining_order' => 0,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 0,
            'transaction_charge' => 0,
            'is_active' => false,
        ]);

        $this->assertTrue($this->service->hasDomainUsedFreeTrial('shop.example.com'));
    }

    public function test_detects_free_trial_from_soft_deleted_package(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'trial2@example.com',
            'phone' => '01700000021',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Free Trial',
            'per_order_rate' => 0,
            'package_price' => 0,
            'package_duration' => 'free_trial',
            'trial_days' => 14,
            'is_active' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Free Trial',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'package_duration' => 'free_trial',
            'plan_type' => 'catalog',
            'total_order_can_handle' => 100,
            'remaining_order' => 0,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 0,
            'transaction_charge' => 0,
            'is_active' => false,
        ]);

        $package->delete();

        $this->assertTrue($this->service->hasDomainUsedFreeTrial('shop.example.com'));
    }
}
