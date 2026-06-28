<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PackageHubCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_catalog_package(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $features = [
            'fraud_customer_checker' => true,
            'one_click_app_connect' => true,
        ];

        $response = $this->actingAs($admin)->post(route('packages.create'), [
            'package_name' => 'Pro Plus',
            'package_duration' => '1_month',
            'trial_days' => null,
            'order_rate_token' => 1000,
            'package_price' => 1500,
            'description' => '<p>Full feature plan</p>',
            'is_active' => true,
            'is_special' => true,
            'app_connect' => true,
            'total_website_connect' => 3,
            'features' => $features,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $package = PackageHub::query()->where('title', 'Pro Plus')->first();
        $this->assertNotNull($package);
        $this->assertSame('1_month', $package->package_duration);
        $this->assertSame(1000, $package->order_rate_token);
        $this->assertSame(1500.0, (float) $package->package_price);
        $this->assertTrue($package->app_connect);
        $this->assertSame(3, $package->total_website_connect);
        $this->assertEquals($features, $package->features);
        $this->assertTrue($package->is_special);
        $this->assertSame(0.0, (float) $package->per_order_rate);
    }

    public function test_free_trial_requires_trial_days(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('packages.create'), [
                'package_name' => 'Trial Plan',
                'package_duration' => 'free_trial',
                'order_rate_token' => 100,
                'package_price' => 0,
                'is_active' => true,
                'app_connect' => false,
                'features' => ['fraud_customer_checker' => true],
            ])
            ->assertSessionHasErrors('trial_days');
    }
}
