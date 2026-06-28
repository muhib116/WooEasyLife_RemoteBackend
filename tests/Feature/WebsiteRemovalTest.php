<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use App\Services\WebsiteAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_website_and_related_data(): void
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

        $plan = PackageHub::create([
            'title' => 'Standard',
            'per_order_rate' => 1,
            'is_active' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $userPackage = UserPackage::create([
            'title' => 'Standard',
            'domain' => 'localhost',
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'website_id' => $website->id,
            'total_order_can_handle' => 100,
            'remaining_order' => 75,
            'total_order_handled' => 25,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        AccessToken::unguarded(function () use ($merchant, $website) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Local License',
                'token' => hash('sha256', 'local-token'),
                'domain' => 'localhost',
                'website_id' => $website->id,
                'status' => true,
            ]);
        });

        PackagePaymentRequest::create([
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'website_id' => $website->id,
            'domain' => 'localhost',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN1',
            'account_number' => '01700000000',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.websites.delete', $merchant->id),
            ['domain' => 'localhost']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('user_packages', ['id' => $userPackage->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $merchant->id,
            'domain' => 'localhost',
        ]);
        $this->assertDatabaseMissing('websites', ['id' => $website->id]);
        $this->assertDatabaseHas('package_payment_requests', [
            'domain' => 'localhost',
            'status' => 'cancelled',
        ]);

        $websites = app(WebsiteAggregatorService::class)->forUser($merchant);
        $this->assertSame([], $websites);
    }
}
