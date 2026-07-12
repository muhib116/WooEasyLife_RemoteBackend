<?php

namespace Tests\Feature;

use App\Models\PackageHub;
use App\Models\PackagePaymentRequest;
use App\Models\SubscriptionInquiry;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LandingOrderConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function adminUser(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    private function plan(): PackageHub
    {
        return PackageHub::create([
            'title' => 'Basic 1 Month',
            'per_order_rate' => 0,
            'package_duration' => '1_month',
            'order_rate_token' => 1000,
            'package_price' => 600,
            'is_active' => true,
            'features' => ['fraud_customer_checker' => true],
        ]);
    }

    public function test_convert_creates_merchant_billing_package_and_license(): void
    {
        $admin = $this->adminUser();
        $plan = $this->plan();

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'convert-shop.test',
            'customer_name' => 'Muhibbullah Ansary',
            'email' => 'convert-buyer@example.com',
            'contact_number' => '01770989591',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 600,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-CONVERT-1',
            'account_number' => '01770989591',
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->actingAs($admin)->post(route('orders.convert', $inquiry));

        $response->assertRedirect();
        $response->assertSessionHas('license_token');
        $response->assertSessionHas('converted_user_id');
        $response->assertSessionHas('success');

        $user = User::query()->where('email', 'convert-buyer@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);
        $this->assertSame('landing_order:'.$inquiry->id, $user->acquisition_source);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue(Hash::check('01770989591', $user->password));

        $this->assertDatabaseHas('websites', [
            'user_id' => $user->id,
            'domain' => 'convert-shop.test',
        ]);

        $this->assertDatabaseHas('user_packages', [
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'convert-shop.test',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('package_payment_requests', [
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'domain' => 'convert-shop.test',
            'status' => 'approved',
            'transaction_id' => 'TXN-CONVERT-1',
        ]);

        $inquiry->refresh();
        $this->assertSame('converted', $inquiry->status);
        $this->assertSame($user->id, $inquiry->user_id);
        $this->assertNotNull($inquiry->converted_at);
        $this->assertNotNull($inquiry->package_payment_request_id);
        $this->assertNotNull($inquiry->converted_access_token_id);
        $this->assertIsArray($inquiry->conversion_meta);
        $this->assertNotEmpty($inquiry->conversion_meta['events'] ?? []);

        Mail::assertSent(\App\Mail\LandingOrderConvertedMail::class);

        $this->assertTrue(
            UserPackage::query()->where('user_id', $user->id)->where('domain', 'convert-shop.test')->exists()
        );

        $this->actingAs($admin)
            ->get(route('orders.show', $inquiry))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Show')
                ->where('order.id', $inquiry->id));
    }

    public function test_convert_reuses_existing_merchant_without_resetting_password(): void
    {
        $admin = $this->adminUser();
        $plan = $this->plan();

        $existing = User::create([
            'name' => 'Existing Merchant',
            'email' => 'existing@example.com',
            'phone' => '01711112222',
            'password' => Hash::make('keep-this-password'),
            'role' => 'user',
            'status' => true,
        ]);

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'existing-shop.test',
            'customer_name' => 'Existing Merchant',
            'email' => 'existing@example.com',
            'contact_number' => '01770989591',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 600,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-EXISTING',
            'account_number' => '01770989591',
            'status' => 'contacted',
            'source' => 'landing_pricing',
        ]);

        $this->actingAs($admin)->post(route('orders.convert', $inquiry))->assertRedirect();

        $existing->refresh();
        $this->assertTrue(Hash::check('keep-this-password', $existing->password));
        $this->assertFalse((bool) $existing->must_change_password);
        $this->assertSame('landing_order:'.$inquiry->id, $existing->acquisition_source);
        $this->assertSame(1, PackagePaymentRequest::query()->where('user_id', $existing->id)->where('status', 'approved')->count());
    }

    public function test_convert_preview_reports_ready_state(): void
    {
        $admin = $this->adminUser();
        $plan = $this->plan();

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'preview-shop.test',
            'customer_name' => 'Preview User',
            'email' => 'preview@example.com',
            'contact_number' => '01770989591',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 600,
            'transaction_method' => 'Bkash',
            'transaction_id' => 'TXN-PREVIEW',
            'account_number' => '01770989591',
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $response = $this->actingAs($admin)->getJson(route('orders.convertPreview', $inquiry));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user_resolution.action', 'create')
            ->assertJsonPath('dns_ok', true)
            ->assertJsonPath('credentials.must_change_password', true);
    }

    public function test_already_converted_without_user_id_is_blocked(): void
    {
        $admin = $this->adminUser();
        $plan = $this->plan();

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'legacy-converted.test',
            'email' => 'legacy@example.com',
            'contact_number' => '01770989591',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 600,
            'status' => 'converted',
            'source' => 'landing_pricing',
        ]);

        $this->actingAs($admin)
            ->post(route('orders.convert', $inquiry))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_status_endpoint_blocks_converted_shortcut(): void
    {
        $admin = $this->adminUser();
        $plan = $this->plan();

        $inquiry = SubscriptionInquiry::create([
            'package_hub_id' => $plan->id,
            'domain' => 'block.test',
            'email' => 'block@example.com',
            'contact_number' => '01770989591',
            'whatsapp_number' => '01770989591',
            'address' => 'Dhaka',
            'order_limit' => 1000,
            'total_amount' => 600,
            'status' => 'pending',
            'source' => 'landing_pricing',
        ]);

        $this->actingAs($admin)
            ->post(route('orders.updateStatus', $inquiry), ['status' => 'converted'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $inquiry->fresh()->status);
    }
}
