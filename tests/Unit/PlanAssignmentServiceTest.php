<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainNormalizer;
use App\Services\PlanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_plan_with_normalized_domain(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturn('shop.example.com');
            $mock->shouldReceive('matches')
                ->andReturnUsing(function (?string $left, ?string $right) {
                    return (new DomainNormalizer())->matches($left, $right);
                });
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) {
                    (new DomainNormalizer())->constrainMatchingDomain($query, $column, $domain);
                });
        });

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant3@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $userPackage = app(PlanAssignmentService::class)->assign($user, $package, [
            'domain' => 'https://shop.example.com',
            'limit' => 300,
            'transaction_method' => 'Cash',
        ]);

        $this->assertSame('shop.example.com', $userPackage->domain);
        $this->assertSame(300, $userPackage->remaining_order);
        $this->assertSame(600.0, (float) $userPackage->total_cost);
    }

    public function test_rejects_legacy_assign_when_domain_owned_by_other_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000020',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'phone' => '01700000021',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(PlanAssignmentService::class)->assign($intruder, $package, [
            'domain' => 'shop.example.com',
            'limit' => 100,
            'transaction_method' => 'Cash',
        ]);
    }

    public function test_allows_same_merchant_second_legacy_assign_on_same_domain(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'repeat@example.com',
            'phone' => '01700000022',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 2,
            'is_active' => true,
        ]);

        app(PlanAssignmentService::class)->assign($user, $package, [
            'domain' => 'shop.example.com',
            'limit' => 100,
            'transaction_method' => 'Cash',
        ]);

        $second = app(PlanAssignmentService::class)->assign($user, $package, [
            'domain' => 'shop.example.com',
            'limit' => 200,
            'transaction_method' => 'Cash',
        ]);

        $this->assertSame('shop.example.com', $second->domain);
        $this->assertSame(2, UserPackage::query()->where('user_id', $user->id)->count());
    }

    public function test_rejects_catalog_assign_when_domain_owned_by_other_merchant(): void
    {
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'catalog-owner@example.com',
            'phone' => '01700000023',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'catalog-intruder@example.com',
            'phone' => '01700000024',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $catalogPlan = PackageHub::create([
            'title' => 'Starter',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 999,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(PlanAssignmentService::class)->assign($intruder, $catalogPlan, [
            'domain' => 'shop.example.com',
            'transaction_method' => 'Cash',
        ]);
    }
}
