<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantSetupWizardTest extends TestCase
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
            $mock->shouldReceive('matches')
                ->andReturnUsing(function (?string $left, ?string $right) {
                    return (new DomainNormalizer())->matches($left, $right);
                });
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) {
                    (new DomainNormalizer())->constrainMatchingDomain($query, $column, $domain);
                });
        });
    }

    public function test_wizard_flow_assigns_plan_and_generates_license_with_matching_domain(): void
    {
        $this->mockDomainNormalizer();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
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

        $this->actingAs($admin)
            ->post(route('users.purchasePackage', $merchant->id), [
                'domain' => 'shop.example.com',
                'limit' => 300,
                'package_id' => $package->id,
                'transaction_method' => 'Cash',
                'transaction_charge' => 0,
                'redirect_to_setup' => true,
            ])
            ->assertRedirect(route('users.setup', [
                'user_id' => $merchant->id,
                'step' => 'license',
                'domain' => 'shop.example.com',
            ]));

        $userPackage = UserPackage::where('user_id', $merchant->id)->first();
        $this->assertNotNull($userPackage);
        $this->assertSame('shop.example.com', $userPackage->domain);

        $response = $this->actingAs($admin)
            ->post(route('users.setup.generateLicense', $merchant->id), [
                'domain' => 'shop.example.com',
                'user_package_id' => $userPackage->id,
                'status' => true,
            ]);

        $response->assertRedirect(route('users.setup', [
            'user_id' => $merchant->id,
            'step' => 'complete',
        ]));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $merchant->id,
            'domain' => 'shop.example.com',
        ]);
    }
}
