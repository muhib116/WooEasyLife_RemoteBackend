<?php

namespace Tests\Unit;

use App\Models\PackageHub;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\WebsiteAggregatorService;
use App\Services\WebsiteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebsiteSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_website_and_links_without_changing_domain_strings(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $userPackage = UserPackage::create([
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

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $stats = app(WebsiteSyncService::class)->backfillUser($user);

        $this->assertSame(1, $stats['websites_created']);
        $this->assertSame(1, $stats['packages_linked']);
        $this->assertSame(1, $stats['tokens_linked']);

        $website = Website::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($website);
        $this->assertSame('shop.example.com', $website->domain);

        $userPackage->refresh();
        $this->assertSame($website->id, $userPackage->website_id);
        $this->assertSame('https://shop.example.com', $userPackage->domain);

        $aggregated = app(WebsiteAggregatorService::class)->forUser($user);
        $this->assertCount(1, $aggregated);
        $this->assertSame($website->id, $aggregated[0]['id']);
    }

    public function test_backfill_is_idempotent(): void
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
            'domain' => 'shop.example.com',
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

        $service = app(WebsiteSyncService::class);
        $first = $service->backfillUser($user);
        $second = $service->backfillUser($user);

        $this->assertSame(1, $first['websites_created']);
        $this->assertSame(0, $second['websites_created']);
        $this->assertSame(1, Website::query()->where('user_id', $user->id)->count());
    }

    public function test_resolve_for_user_creates_website_for_new_domain(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'new-domain@example.com',
            'phone' => '01700000010',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = app(WebsiteSyncService::class)->resolveForUser($user, 'shop.example.com', 'My Shop');

        $this->assertNotNull($website);
        $this->assertSame('shop.example.com', $website->domain);
        $this->assertSame($user->id, $website->user_id);
        $this->assertTrue($website->is_primary);
    }

    public function test_resolve_for_user_returns_existing_website_for_same_user(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'same-user@example.com',
            'phone' => '01700000011',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $existing = Website::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $website = app(WebsiteSyncService::class)->resolveForUser($user, 'shop.example.com');

        $this->assertSame($existing->id, $website->id);
        $this->assertSame(1, Website::query()->where('domain', 'shop.example.com')->count());
    }

    public function test_resolve_for_user_persists_optional_base_url(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-base-url@example.com',
            'phone' => '01700000088',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = app(WebsiteSyncService::class)->resolveForUser(
            $user,
            'localhost',
            'Local WordPress',
            'http://localhost:8081/wordpress/'
        );

        $this->assertNotNull($website);
        $this->assertSame('localhost', $website->domain);
        $this->assertSame('http://localhost:8081/wordpress', $website->base_url);
    }

    public function test_sync_base_url_for_domain_updates_existing_website(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-sync-base@example.com',
            'phone' => '01700000087',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $user->id,
            'domain' => 'localhost',
            'title' => 'Local',
            'status' => true,
        ]);

        $website = app(WebsiteSyncService::class)->syncBaseUrlForDomain(
            $user,
            'localhost',
            'http://localhost:8081/wordpress'
        );

        $this->assertSame('http://localhost:8081/wordpress', $website?->base_url);
    }

    public function test_sync_base_url_rejects_host_mismatch_with_store_domain(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-host-mismatch@example.com',
            'phone' => '01700000086',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $user->id,
            'domain' => 'localhost',
            'title' => 'Local',
            'status' => true,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(WebsiteSyncService::class)->syncBaseUrlForDomain(
                $user,
                'localhost',
                'http://evil.example.com/wordpress'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('base_url', $exception->errors());

            throw $exception;
        }
    }

    public function test_resolve_for_user_rejects_invalid_base_url(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-invalid-base@example.com',
            'phone' => '01700000085',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(WebsiteSyncService::class)->resolveForUser(
                $user,
                'localhost',
                'Local WordPress',
                'not-a-valid-base-url'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('base_url', $exception->errors());

            throw $exception;
        }
    }

    public function test_resolve_for_user_rejects_domain_owned_by_other_merchant(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000012',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'phone' => '01700000013',
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

        $this->expectException(ValidationException::class);

        app(WebsiteSyncService::class)->resolveForUser($intruder, 'shop.example.com');
    }

    public function test_resolve_for_user_rejects_wrong_owner_even_when_kill_switch_off(): void
    {
        config(['domains.enforce_global_uniqueness' => false]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner3@example.com',
            'phone' => '01700000016',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder3@example.com',
            'phone' => '01700000017',
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

        $this->expectException(ValidationException::class);

        app(WebsiteSyncService::class)->resolveForUser($intruder, 'shop.example.com');
    }

    public function test_backfill_skips_domain_owned_by_another_merchant(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner2@example.com',
            'phone' => '01700000014',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder2@example.com',
            'phone' => '01700000015',
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

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $intruder->id,
            'package_hub_id' => PackageHub::create([
                'title' => 'Standard',
                'per_order_rate' => 1,
                'is_active' => true,
            ])->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $stats = app(WebsiteSyncService::class)->backfillUser($intruder);

        $this->assertSame(0, $stats['websites_created']);
        $this->assertSame(1, $stats['websites_skipped']);
        $this->assertDatabaseMissing('websites', [
            'user_id' => $intruder->id,
            'domain' => 'shop.example.com',
        ]);
    }
}
