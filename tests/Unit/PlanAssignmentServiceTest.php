<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\User;
use App\Services\DomainNormalizer;
use App\Services\PlanAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlanAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_plan_with_normalized_domain(): void
    {
        $this->mock(DomainNormalizer::class, function ($mock) {
            $mock->shouldReceive('normalize')->andReturn('shop.example.com');
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
}
