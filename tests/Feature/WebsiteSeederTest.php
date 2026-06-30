<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_website_seeder_backfills_from_existing_domain_data(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
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

        $this->assertDatabaseCount('websites', 0);

        $this->seed(WebsiteSeeder::class);

        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $website = Website::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($website);
        $this->assertSame($website->id, $userPackage->fresh()->website_id);
    }
}
