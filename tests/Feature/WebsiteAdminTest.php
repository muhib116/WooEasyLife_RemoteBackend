<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WebsiteAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_website_details(): void
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

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.websites.update', $merchant->id),
            [
                'website_id' => $website->id,
                'title' => 'Local WordPress (8081)',
                'base_url' => 'http://localhost:8081/wordpress',
                'status' => true,
                'is_primary' => true,
            ]
        );

        $response->assertRedirect();

        $website->refresh();

        $this->assertSame('Local WordPress (8081)', $website->title);
        $this->assertSame('http://localhost:8081/wordpress', $website->base_url);
    }

    public function test_admin_cannot_update_website_for_another_merchant(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000099',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $other = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $owner->id,
            'domain' => 'shop.example.com',
            'title' => 'shop.example.com',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.websites.update', $other->id),
            [
                'website_id' => $website->id,
                'title' => 'Hijacked',
            ]
        );

        $response->assertNotFound();

        $this->assertSame('shop.example.com', $website->fresh()->title);
    }

    public function test_update_website_rejects_base_url_host_mismatch(): void
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

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.websites.update', $merchant->id),
            [
                'website_id' => $website->id,
                'base_url' => 'http://evil.example.com/wordpress',
            ]
        );

        $response->assertSessionHasErrors('base_url');
    }

    public function test_admin_can_clear_base_url_and_deactivate_website(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-clear@example.com',
            'phone' => '01700000098',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-clear@example.com',
            'phone' => '01700000097',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'Local',
            'base_url' => 'http://localhost:8081/wordpress',
            'status' => true,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(
            route('users.websites.update', $merchant->id),
            [
                'website_id' => $website->id,
                'title' => 'Local',
                'base_url' => '',
                'status' => false,
                'is_primary' => true,
            ]
        );

        $response->assertRedirect()
            ->assertSessionHas('success');

        $website->refresh();

        $this->assertNull($website->base_url);
        $this->assertFalse($website->status);
    }

    public function test_updated_website_is_reflected_in_aggregator_payload(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-agg@example.com',
            'phone' => '01700000096',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-agg@example.com',
            'phone' => '01700000095',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'Local',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->post(
            route('users.websites.update', $merchant->id),
            [
                'website_id' => $website->id,
                'title' => 'Local WordPress',
                'base_url' => 'http://localhost:8081/wordpress',
                'status' => true,
                'is_primary' => true,
            ]
        )->assertRedirect();

        $payload = collect(app(\App\Services\WebsiteAggregatorService::class)->forUser($merchant))
            ->firstWhere('id', $website->id);

        $this->assertNotNull($payload);
        $this->assertSame('Local WordPress', $payload['title']);
        $this->assertSame('http://localhost:8081/wordpress', $payload['base_url']);
        $this->assertSame('http://localhost:8081/wordpress', $payload['display_url']);
    }
}
