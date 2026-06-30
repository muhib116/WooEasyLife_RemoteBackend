<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\SmsBalance;
use App\Models\UserBusiness;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private DomainAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['domains.enforce_global_uniqueness' => true]);
        $this->service = app(DomainAvailabilityService::class);
    }

    private function merchant(string $email): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => $email,
            'phone' => uniqid('01'),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_unclaimed_domain_is_available(): void
    {
        $user = $this->merchant('free@example.com');

        $this->assertTrue($this->service->isAvailableForUser($user, 'shop.example.com'));
        $this->assertNull($this->service->findOwnerUserId('shop.example.com'));
    }

    public function test_same_user_existing_website_is_available(): void
    {
        $user = $this->merchant('owner@example.com');

        Website::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->assertTrue($this->service->isAvailableForUser($user, 'shop.example.com'));
        $this->service->assertAvailableForUser($user, 'shop.example.com');
    }

    public function test_reject_duplicate_website_for_user(): void
    {
        $user = $this->merchant('dup@example.com');

        Website::create([
            'user_id' => $user->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->rejectDuplicateWebsiteForUser($user, 'localhost');
    }

    public function test_other_users_website_is_blocked(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
        $this->assertSame($owner->id, $this->service->findOwnerUserId('shop.example.com'));

        try {
            $this->service->assertAvailableForUser($intruder, 'shop.example.com', forAdmin: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already registered', $e->errors()['domain'][0]);
            $this->assertStringContainsString("#{$owner->id}", $e->errors()['domain'][0]);
        }
    }

    public function test_other_users_package_without_website_is_blocked(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
    }

    public function test_other_users_access_token_blocks_domain(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        AccessToken::unguarded(function () use ($owner) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $owner->id,
                'name' => 'License',
                'token' => hash('sha256', 'token-' . uniqid()),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
    }

    public function test_soft_deleted_package_does_not_block_domain(): void
    {
        $owner = $this->merchant('owner@example.com');
        $next = $this->merchant('next@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $package->delete();

        $this->assertTrue($this->service->isAvailableForUser($next, 'shop.example.com'));
    }

    public function test_kill_switch_disables_enforcement(): void
    {
        config(['domains.enforce_global_uniqueness' => false]);

        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->assertTrue($this->service->isAvailableForUser($intruder, 'shop.example.com'));
        $this->service->assertAvailableForUser($intruder, 'shop.example.com');
    }

    public function test_find_cross_user_conflicts_detects_duplicates(): void
    {
        $first = $this->merchant('first@example.com');
        $second = $this->merchant('second@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $first->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $second->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $conflicts = $this->service->findCrossUserConflicts();

        $this->assertCount(1, $conflicts);
        $this->assertSame('shop.example.com', $conflicts[0]['domain']);
        $this->assertCount(2, $conflicts[0]['user_ids']);
        $this->assertArrayHasKey('user_packages', $conflicts[0]['sources']);
    }

    public function test_find_cross_user_conflicts_empty_for_single_merchant(): void
    {
        $user = $this->merchant('solo@example.com');

        Website::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        Website::create([
            'user_id' => $user->id,
            'domain' => 'other.example.com',
            'title' => 'other.example.com',
            'status' => true,
            'is_primary' => false,
        ]);

        $this->assertSame([], $this->service->findCrossUserConflicts());
    }

    public function test_invalid_domain_is_not_available(): void
    {
        $user = $this->merchant('invalid@example.com');

        $this->assertFalse($this->service->isAvailableForUser($user, ''));
        $this->assertNull($this->service->normalize(''));
    }

    public function test_assert_rejects_invalid_domain(): void
    {
        $user = $this->merchant('invalid@example.com');

        try {
            $this->service->assertAvailableForUser($user, '');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Invalid domain', $e->errors()['domain'][0]);
        }
    }

    public function test_user_facing_message_omits_merchant_id(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        try {
            $this->service->assertAvailableForUser($intruder, 'shop.example.com', forAdmin: false);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $message = $e->errors()['domain'][0];
            $this->assertStringContainsString('already registered', $message);
            $this->assertStringNotContainsString('#', $message);
        }
    }

    public function test_legacy_non_normalized_package_domain_blocks_intruder(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://Shop.Example.com',
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
        $this->assertSame($owner->id, $this->service->findOwnerUserId('shop.example.com'));
    }

    public function test_legacy_non_normalized_token_domain_blocks_intruder(): void
    {
        $owner = $this->merchant('owner@example.com');
        $intruder = $this->merchant('intruder@example.com');

        AccessToken::unguarded(function () use ($owner) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $owner->id,
                'name' => 'License',
                'token' => hash('sha256', 'token-' . uniqid()),
                'domain' => 'HTTPS://Shop.Example.com',
                'status' => true,
            ]);
        });

        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
    }

    public function test_website_owner_takes_priority_over_package(): void
    {
        $websiteOwner = $this->merchant('website-owner@example.com');
        $packageOwner = $this->merchant('package-owner@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        Website::create([
            'user_id' => $websiteOwner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $packageOwner->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->assertSame($websiteOwner->id, $this->service->findOwnerUserId('shop.example.com'));
    }

    public function test_soft_deleted_website_does_not_block_when_only_package_remains_deleted(): void
    {
        $owner = $this->merchant('owner@example.com');
        $next = $this->merchant('next@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $package = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $owner->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $website = Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $package->delete();
        $website->delete();

        $this->assertTrue($this->service->isAvailableForUser($next, 'shop.example.com'));
    }

    public function test_same_user_second_package_on_same_domain_is_allowed(): void
    {
        $user = $this->merchant('repeat@example.com');

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->assertTrue($this->service->isAvailableForUser($user, 'shop.example.com'));
        $this->service->assertAvailableForUser($user, 'shop.example.com');
    }

    public function test_is_enforcement_enabled_reflects_config(): void
    {
        $this->assertTrue($this->service->isEnforcementEnabled());

        config(['domains.enforce_global_uniqueness' => false]);

        $this->assertFalse($this->service->isEnforcementEnabled());
    }

    public function test_user_business_domain_claims_owner(): void
    {
        $owner = $this->merchant('business-owner@example.com');
        $intruder = $this->merchant('business-intruder@example.com');

        UserBusiness::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'Shop',
        ]);

        $this->assertSame($owner->id, $this->service->findOwnerUserId('shop.example.com'));
        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
    }

    public function test_sms_balance_domain_claims_owner(): void
    {
        $owner = $this->merchant('sms-owner@example.com');
        $intruder = $this->merchant('sms-intruder@example.com');

        SmsBalance::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'amount' => 10,
        ]);

        $this->assertSame($owner->id, $this->service->findOwnerUserId('shop.example.com'));
        $this->assertFalse($this->service->isAvailableForUser($intruder, 'shop.example.com'));
    }

    public function test_reject_cross_user_website_claim_always_enforced(): void
    {
        config(['domains.enforce_global_uniqueness' => false]);

        $owner = $this->merchant('website-owner@example.com');
        $intruder = $this->merchant('website-intruder@example.com');

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->rejectCrossUserWebsiteClaim($intruder, 'shop.example.com');
    }
}
