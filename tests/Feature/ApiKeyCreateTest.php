<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\DomainNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiKeyCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturn('shop.example.com');
            $mock->shouldReceive('hasDnsARecord')->andReturn(true);
        });
    }

    public function test_global_create_requires_linked_plan(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000099',
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

        $response = $this->actingAs($admin)->post(route('apiKeys.create'), [
            'tokenable_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'status' => true,
        ]);

        $response->assertSessionHasErrors('user_package_id');
    }

    public function test_global_create_succeeds_with_matching_plan(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'phone' => '01700000098',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
            'index' => 1,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('apiKeys.create'), [
            'tokenable_id' => $merchant->id,
            'user_package_id' => $userPackage->id,
            'domain' => 'shop.example.com',
            'status' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'user_package_id' => $userPackage->id,
        ]);
    }
}
