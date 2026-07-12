<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainNormalizer;
use App\Services\LicenseProvisioningService;
use App\Services\PlanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LicenseProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function mockDomainNormalizer(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturnUsing(function (?string $input) {
                if ($input === null || trim($input) === '') {
                    return null;
                }

                return 'shop.example.com';
            });
            $mock->shouldReceive('matches')->andReturnUsing(function (?string $left, ?string $right) {
                $normalize = fn (?string $value) => $value ? 'shop.example.com' : null;

                return $normalize($left) === $normalize($right);
            });
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
            $mock->shouldReceive('resolvesPublicly')->andReturn(true);
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) {
                    (new DomainNormalizer())->constrainMatchingDomain($query, $column, $domain);
                });
        });
    }

    public function test_creates_license_for_matching_user_package_domain(): void
    {
        $this->mockDomainNormalizer();

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $result = app(LicenseProvisioningService::class)->create(
            $user,
            'https://shop.example.com',
            ['user_package_id' => $userPackage->id],
        );

        $this->assertNotEmpty($result['plain_text_token']);
        $this->assertSame('shop.example.com', $result['access_token']->domain);
        $this->assertSame($userPackage->id, $result['user_package']->id);
    }

    public function test_rejects_license_when_no_matching_plan(): void
    {
        $this->mockDomainNormalizer();

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(LicenseProvisioningService::class)->create(
            $user,
            'shop.example.com',
            [],
            requireUserPackage: true,
        );
    }

    public function test_rejects_license_create_when_domain_owned_by_other_merchant(): void
    {
        $this->mockDomainNormalizer();
        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000030',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'phone' => '01700000031',
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
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $intruderPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'other.example.com',
            'user_id' => $intruder->id,
            'package_hub_id' => $package->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(LicenseProvisioningService::class)->create(
            $intruder,
            'shop.example.com',
            ['user_package_id' => $intruderPackage->id],
        );
    }

    public function test_rejects_license_update_when_changing_to_taken_domain(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturnUsing(function (?string $input) {
                if ($input === null || trim($input) === '') {
                    return null;
                }

                return strtolower(trim(str_replace(['https://', 'http://'], '', $input)));
            });
            $mock->shouldReceive('matches')->andReturnUsing(function (?string $left, ?string $right) {
                $normalize = fn (?string $value) => $value
                    ? strtolower(trim(str_replace(['https://', 'http://'], '', $value)))
                    : null;

                return $normalize($left) === $normalize($right);
            });
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
            $mock->shouldReceive('resolvesPublicly')->andReturn(true);
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) {
                    (new DomainNormalizer())->constrainMatchingDomain($query, $column, $domain);
                });
        });

        config(['domains.enforce_global_uniqueness' => true]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner2@example.com',
            'phone' => '01700000032',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder2@example.com',
            'phone' => '01700000033',
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

        $token = AccessToken::unguarded(function () use ($intruder) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $intruder->id,
                'name' => 'License',
                'token' => hash('sha256', 'token-' . uniqid()),
                'domain' => 'other.example.com',
                'status' => true,
            ]);
        });

        $this->expectException(ValidationException::class);

        app(LicenseProvisioningService::class)->update($token, [
            'domain' => 'shop.example.com',
        ]);
    }

    public function test_allows_license_update_when_domain_is_unchanged(): void
    {
        $this->mockDomainNormalizer();
        config(['domains.enforce_global_uniqueness' => true]);

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'unchanged@example.com',
            'phone' => '01700000034',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $token = AccessToken::unguarded(function () use ($user) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token-' . uniqid()),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $updated = app(LicenseProvisioningService::class)->update($token, [
            'title' => 'Updated title',
            'domain' => 'shop.example.com',
        ]);

        $this->assertSame('Updated title', $updated->title);
        $this->assertSame('shop.example.com', $updated->domain);
    }
}
