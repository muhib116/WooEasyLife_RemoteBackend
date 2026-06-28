<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\WebsiteAggregatorService;
use App\Services\WebsiteSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
}
