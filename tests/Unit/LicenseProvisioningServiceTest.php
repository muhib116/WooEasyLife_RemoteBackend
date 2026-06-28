<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use App\Services\LicenseProvisioningService;
use App\Services\PlanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
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
}
