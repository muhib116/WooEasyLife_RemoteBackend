<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainAvailabilityService;
use App\Services\WebsiteRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteRemovalServiceDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_remove_frees_domain_for_another_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000040',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $next = User::create([
            'name' => 'Next',
            'email' => 'next@example.com',
            'phone' => '01700000041',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $website = Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'website_id' => $website->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($owner, $website) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $owner->id,
                'name' => 'License',
                'token' => hash('sha256', 'token-' . uniqid()),
                'domain' => 'shop.example.com',
                'website_id' => $website->id,
                'status' => true,
            ]);
        });

        PackagePaymentRequest::create([
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'website_id' => $website->id,
            'domain' => 'shop.example.com',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-1',
            'account_number' => '01700000040',
            'status' => 'pending',
        ]);

        app(WebsiteRemovalService::class)->remove($owner, 'shop.example.com');

        $availability = app(DomainAvailabilityService::class);

        $this->assertTrue($availability->isAvailableForUser($next, 'shop.example.com'));
        $this->assertNull($availability->findOwnerUserId('shop.example.com'));
    }
}
