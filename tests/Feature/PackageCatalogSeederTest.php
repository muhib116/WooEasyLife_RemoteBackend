<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\User;
use Database\Seeders\PackageCatalogSeeder;
use Database\Seeders\RequiredTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PackageCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_creates_catalog_format_packages(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->seed(PackageCatalogSeeder::class);

        $proMonthly = PackageHub::query()->where('title', 'Pro Plus – 1 Month')->first();
        $this->assertNotNull($proMonthly);
        $this->assertSame('1_month', $proMonthly->package_duration);
        $this->assertSame(10000, $proMonthly->order_rate_token);
        $this->assertSame(4999.0, (float) $proMonthly->package_price);
        $this->assertTrue($proMonthly->is_special);
        $this->assertTrue($proMonthly->app_connect);
        $this->assertSame(3, $proMonthly->total_website_connect);
        $this->assertIsArray($proMonthly->features);
        $this->assertTrue($proMonthly->features['fraud_customer_checker'] ?? false);
        $this->assertSame(0.0, (float) $proMonthly->per_order_rate);

        $trial = PackageHub::query()->where('title', 'Free Trial')->first();
        $this->assertNotNull($trial);
        $this->assertSame('free_trial', $trial->package_duration);
        $this->assertSame(14, $trial->trial_days);
        $this->assertFalse($trial->app_connect);
        $this->assertFalse($trial->features['ai_intelligence'] ?? true);

        $this->assertSame(5, PackageHub::query()->whereNotNull('package_duration')->count());
        $this->assertSame(0, PackageHub::query()
            ->whereNull('package_duration')
            ->where('is_active', true)
            ->count());
    }

    public function test_catalog_seeder_retires_unassigned_legacy_packages(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin-legacy@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        PackageHub::create([
            'title' => 'Standard',
            'description' => 'Legacy plan',
            'per_order_rate' => 1,
            'is_active' => true,
            'index' => 99,
        ]);

        $this->seed(PackageCatalogSeeder::class);

        $legacy = PackageHub::query()->where('title', 'Standard')->first();
        $this->assertNotNull($legacy);
        $this->assertFalse($legacy->is_active);
    }

    public function test_catalog_seeder_is_idempotent(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->seed(PackageCatalogSeeder::class);
        $count = PackageHub::count();

        $this->seed(PackageCatalogSeeder::class);

        $this->assertSame($count, PackageHub::count());
    }

    public function test_required_table_seeder_includes_catalog_packages(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->seed(RequiredTableSeeder::class);

        $this->assertNotNull(PackageHub::query()->where('title', 'Pro Plus – 1 Month')->first());
        $this->assertGreaterThanOrEqual(5, PackageHub::query()->where('is_active', true)->count());
    }
}
