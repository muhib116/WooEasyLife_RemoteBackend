<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WebsiteAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_another_website_when_primary_is_cleared(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $primary = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'title' => 'Shop A',
            'status' => true,
            'is_primary' => true,
        ]);

        $secondary = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'title' => 'Shop B',
            'status' => true,
            'is_primary' => false,
        ]);

        app(WebsiteAdminService::class)->update($merchant, $secondary, [
            'is_primary' => true,
        ]);

        $this->assertFalse($primary->fresh()->is_primary);
        $this->assertTrue($secondary->fresh()->is_primary);
    }

    public function test_demoting_primary_promotes_a_different_website(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-demote@example.com',
            'phone' => '01700000003',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $primary = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'title' => 'Shop A',
            'status' => true,
            'is_primary' => true,
        ]);

        $secondary = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'title' => 'Shop B',
            'status' => true,
            'is_primary' => false,
        ]);

        app(WebsiteAdminService::class)->update($merchant, $primary, [
            'is_primary' => false,
        ]);

        $this->assertFalse($primary->fresh()->is_primary);
        $this->assertTrue($secondary->fresh()->is_primary);
    }

    public function test_cannot_remove_primary_flag_from_only_website(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'only@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop.example.com',
            'title' => 'Shop',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(WebsiteAdminService::class)->update($merchant, $website, [
            'is_primary' => false,
        ]);
    }
}
