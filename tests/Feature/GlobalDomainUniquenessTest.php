<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GlobalDomainUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function mockDomainNormalizer(): void
    {
        $this->mock(\App\Services\DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturnUsing(function (?string $input) {
                if ($input === null || trim($input) === '') {
                    return null;
                }

                return strtolower(trim($input));
            });
            $mock->shouldReceive('matches')->andReturnUsing(function (?string $left, ?string $right) {
                $normalizedLeft = $left ? strtolower(trim($left)) : null;
                $normalizedRight = $right ? strtolower(trim($right)) : null;

                return $normalizedLeft !== null
                    && $normalizedRight !== null
                    && $normalizedLeft === $normalizedRight;
            });
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
            $mock->shouldReceive('constrainMatchingDomain')
                ->andReturnUsing(function ($query, $column, $domain) {
                    (new \App\Services\DomainNormalizer())->constrainMatchingDomain($query, $column, $domain);
                });
        });
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    private function merchant(string $email, string $phone): User
    {
        return User::create([
            'name' => 'Merchant',
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_merchant_b_cannot_validate_domain_already_used_by_merchant_a(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchantA = $this->merchant('merchant-a@example.com', '01700000001');
        $merchantB = $this->merchant('merchant-b@example.com', '01700000002');

        Website::create([
            'user_id' => $merchantA->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->postJson(
            route('users.setup.validateDomain', $merchantB->id),
            ['domain' => 'shop.example.com']
        );

        $response->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'already registered'));
    }

    public function test_merchant_b_cannot_purchase_plan_for_taken_domain(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchantA = $this->merchant('merchant-a@example.com', '01700000003');
        $merchantB = $this->merchant('merchant-b@example.com', '01700000004');

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        Website::create([
            'user_id' => $merchantA->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.purchasePackage', $merchantB->id),
            [
                'domain' => 'shop.example.com',
                'limit' => 100,
                'package_id' => $package->id,
                'transaction_method' => 'Cash',
                'transaction_charge' => 0,
            ]
        );

        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseMissing('user_packages', [
            'user_id' => $merchantB->id,
            'domain' => 'shop.example.com',
        ]);
    }

    public function test_merchant_a_can_repeat_setup_for_same_domain(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchantA = $this->merchant('merchant-a@example.com', '01700000005');

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('users.purchasePackage', $merchantA->id), [
                'domain' => 'shop.example.com',
                'limit' => 100,
                'package_id' => $package->id,
                'transaction_method' => 'Cash',
                'transaction_charge' => 0,
                'redirect_to_setup' => true,
            ])
            ->assertRedirect();

        $userPackage = UserPackage::where('user_id', $merchantA->id)->first();
        $this->assertNotNull($userPackage);

        $this->actingAs($admin)
            ->post(route('users.setup.generateLicense', $merchantA->id), [
                'domain' => 'shop.example.com',
                'user_package_id' => $userPackage->id,
                'status' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $merchantA->id,
            'domain' => 'shop.example.com',
        ]);
    }

    public function test_admin_delete_frees_domain_for_another_merchant(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchantA = $this->merchant('merchant-a@example.com', '01700000006');
        $merchantB = $this->merchant('merchant-b@example.com', '01700000007');

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchantA->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchantA->id,
            'package_hub_id' => $package->id,
            'website_id' => $website->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 100,
            'total_order_handled' => 0,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(
            route('users.websites.delete', $merchantA->id),
            ['domain' => 'shop.example.com']
        )->assertRedirect();

        $service = app(DomainAvailabilityService::class);
        $this->assertTrue($service->isAvailableForUser($merchantB, 'shop.example.com'));

        $this->actingAs($admin)->postJson(
            route('users.setup.validateDomain', $merchantB->id),
            ['domain' => 'shop.example.com']
        )->assertOk()->assertJsonPath('valid', true);
    }

    public function test_merchant_cannot_validate_duplicate_website_when_adding(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchant = $this->merchant('merchant-dup@example.com', '01700000010');

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->postJson(
            route('users.setup.validateDomain', $merchant->id),
            [
                'domain' => 'localhost',
                'require_new_website' => true,
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'already has a website'));

        $this->actingAs($admin)->postJson(
            route('users.setup.validateDomain', $merchant->id),
            ['domain' => 'localhost']
        )->assertOk()->assertJsonPath('valid', true);
    }

    public function test_merchant_cannot_purchase_duplicate_website_when_adding(): void
    {
        $this->mockDomainNormalizer();

        $admin = $this->admin();
        $merchant = $this->merchant('merchant-dup2@example.com', '01700000011');

        $package = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->post(
            route('users.purchasePackage', $merchant->id),
            [
                'domain' => 'localhost',
                'limit' => 100,
                'package_id' => $package->id,
                'transaction_method' => 'Cash',
                'transaction_charge' => 0,
                'require_new_website' => true,
            ]
        )->assertSessionHasErrors('domain');
    }

    public function test_audit_command_fails_when_conflicts_exist(): void
    {
        $first = $this->merchant('first@example.com', '01700000008');
        $second = $this->merchant('second@example.com', '01700000009');

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

        $this->artisan('domains:audit-global-uniqueness')
            ->assertExitCode(1);
    }

    public function test_audit_command_passes_when_no_conflicts(): void
    {
        $merchant = $this->merchant('solo@example.com', '01700000010');

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->artisan('domains:audit-global-uniqueness')
            ->assertExitCode(0);
    }
}
