<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserPackage;
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

    public function test_renames_domain_and_keeps_the_same_license_token(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'rename@example.com',
            'phone' => '01700000011',
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
            'base_url' => 'http://localhost:8081/wordpress',
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

        $token = AccessToken::unguarded(fn () => AccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $merchant->id,
            'name' => 'Local License',
            'token' => hash('sha256', 'keep-this-license'),
            'domain' => 'localhost',
            'website_id' => $website->id,
            'status' => true,
        ]));

        PackagePaymentRequest::create([
            'user_id' => $merchant->id,
            'package_hub_id' => $plan->id,
            'website_id' => $website->id,
            'domain' => 'localhost',
            'order_limit' => 100,
            'total_amount' => 100,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-RENAME',
            'account_number' => '01700000011',
            'status' => 'pending',
        ]);

        SmsBalance::create([
            'user_id' => $merchant->id,
            'type' => 'in',
            'amount' => 10,
            'domain' => 'localhost',
        ]);

        $updated = app(WebsiteAdminService::class)->update($merchant, $website, [
            'domain' => '127.0.0.1',
        ]);

        $this->assertSame('127.0.0.1', $updated->domain);
        $this->assertSame('127.0.0.1', $updated->title);
        $this->assertSame('http://127.0.0.1:8081/wordpress', $updated->base_url);
        $this->assertSame('127.0.0.1', $userPackage->fresh()->domain);
        $this->assertSame($website->id, $userPackage->fresh()->website_id);
        $this->assertSame('127.0.0.1', $token->fresh()->domain);
        $this->assertSame(hash('sha256', 'keep-this-license'), $token->fresh()->token);
        $this->assertSame($token->id, $token->fresh()->id);
        $this->assertDatabaseHas('package_payment_requests', [
            'transaction_id' => 'TXN-RENAME',
            'domain' => '127.0.0.1',
        ]);
        $this->assertDatabaseHas('sms_balances', [
            'user_id' => $merchant->id,
            'domain' => '127.0.0.1',
        ]);
    }

    public function test_cannot_rename_domain_onto_another_website_of_the_same_merchant(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'collide@example.com',
            'phone' => '01700000012',
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

        Website::create([
            'user_id' => $merchant->id,
            'domain' => '127.0.0.1',
            'title' => 'Loopback',
            'status' => true,
            'is_primary' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(WebsiteAdminService::class)->update($merchant, $website, [
            'domain' => '127.0.0.1',
        ]);
    }

    public function test_cannot_rename_domain_onto_another_merchants_website(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner-domain@example.com',
            'phone' => '01700000013',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'intruder-domain@example.com',
            'phone' => '01700000014',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $owner->id,
            'domain' => '127.0.0.1',
            'title' => 'Owner shop',
            'status' => true,
            'is_primary' => true,
        ]);

        $website = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'localhost',
            'title' => 'localhost',
            'status' => true,
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(WebsiteAdminService::class)->update($merchant, $website, [
            'domain' => '127.0.0.1',
        ]);
    }
}
