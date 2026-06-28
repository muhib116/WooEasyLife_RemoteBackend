<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\DomainNormalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DomainNormalizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_legacy_domain_strings_for_merchant(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://Shop.Example.com/path',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'http://shop.example.com',
                'status' => true,
            ]);
        });

        Website::create([
            'user_id' => $user->id,
            'domain' => 'https://shop.example.com',
            'title' => 'Shop',
        ]);

        $stats = app(DomainNormalizationService::class)->normalizeUser($user);

        $this->assertSame(1, $stats['packages_updated']);
        $this->assertSame(1, $stats['tokens_updated']);
        $this->assertSame(1, $stats['websites_updated']);

        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'domain' => 'shop.example.com',
        ]);
        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
        ]);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'https://shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $stats = app(DomainNormalizationService::class)->normalizeUser($user, dryRun: true);

        $this->assertSame(1, $stats['packages_updated']);
        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'domain' => 'https://shop.example.com',
        ]);
    }
}
