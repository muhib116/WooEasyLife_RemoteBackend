<?php

namespace Tests\Feature;

use App\Mail\SubscriptionAlertMail;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchantWithToken(string $domain = 'shop.example.com'): array
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'test-token-' . bin2hex(random_bytes(16));

        AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Test Token',
                'token' => hash('sha256', $plainToken),
                'domain' => $domain,
                'status' => true,
            ]);
        });

        $token = AccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();

        return [$user, $token, $plainToken];
    }

    public function test_notify_command_sends_email_for_warning_alerts(): void
    {
        Mail::fake();
        config([
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => true,
            'subscription.notifications.min_severity' => 'warning',
        ]);

        [$user, $token] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])
            ->assertSuccessful();

        Mail::assertSent(SubscriptionAlertMail::class, function (SubscriptionAlertMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'quota_low',
            'channel' => 'email',
        ]);
    }

    public function test_notify_command_sends_early_sms_for_plan_expiring_in_three_days(): void
    {
        Mail::fake();
        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 202,
                'message_id' => 99,
                'success_message' => 'SMS Submitted Successfully 1',
                'error_message' => '',
            ], 200),
        ]);
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
            'subscription.notifications.sms_expiry_days' => [7, 3, 1, 0],
            'subscription.notifications.min_severity' => 'warning',
        ]);

        [$user] = $this->createMerchantWithToken();

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
            'expires_at' => now()->addDays(3)->endOfDay(),
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])
            ->assertSuccessful();

        Http::assertSent(function ($request) use ($user) {
            $message = (string) $request['message'];

            return str_contains($request->url(), 'bulksmsbd.net')
                && str_contains((string) $request['number'], '017')
                && str_contains($message, '3 দিনের মধ্যে')
                && str_contains($message, 'প্ল্যানটি Renew')
                && ! str_contains($message, 'http')
                && ! str_contains($message, '01770989591');
        });

        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'subscription_expiring',
            'channel' => 'sms',
        ]);
    }

    public function test_notify_command_skips_sms_when_plan_is_not_on_an_early_reminder_day(): void
    {
        Http::fake();
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
            'subscription.notifications.sms_expiry_days' => [7, 3, 1, 0],
        ]);

        [$user] = $this->createMerchantWithToken();

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
            'expires_at' => now()->addDays(5)->endOfDay(),
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('subscription_alert_logs', [
            'user_id' => $user->id,
            'channel' => 'sms',
        ]);
    }

    public function test_notify_command_sends_sms_for_license_token_expiring_tomorrow(): void
    {
        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 202,
                'message_id' => 100,
                'success_message' => 'SMS Submitted Successfully 1',
                'error_message' => '',
            ], 200),
        ]);
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
            'subscription.notifications.sms_expiry_days' => [7, 3, 1, 0],
        ]);

        [$user, $token] = $this->createMerchantWithToken();
        $token->forceFill(['expires_at' => now()->addDay()->endOfDay()])->save();

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
            'expires_at' => now()->addMonths(2),
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $message = (string) $request['message'];

            return str_contains($message, 'লাইসেন্স টোকেনের')
                && str_contains($message, '1 দিনের মধ্যে')
                && ! str_contains($message, 'http');
        });
        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'license_expiring',
            'channel' => 'sms',
        ]);
    }

    public function test_expired_plan_sms_is_sent_once_not_every_day(): void
    {
        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 202,
                'message_id' => 101,
                'success_message' => 'SMS Submitted Successfully 1',
                'error_message' => '',
            ], 200),
        ]);
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
        ]);

        [$user] = $this->createMerchantWithToken();

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
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('subscription_alert_logs', 1);
    }

    public function test_same_day_plan_and_license_expiry_sends_one_sms(): void
    {
        Http::fake([
            'bulksmsbd.net/*' => Http::response([
                'response_code' => 202,
                'message_id' => 102,
                'success_message' => 'SMS Submitted Successfully 1',
                'error_message' => '',
            ], 200),
        ]);
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
            'subscription.notifications.sms_expiry_days' => [7, 3, 1, 0],
        ]);

        [$user, $token] = $this->createMerchantWithToken();
        $expires = now()->addDays(3)->endOfDay();
        $token->forceFill(['expires_at' => $expires])->save();

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
            'expires_at' => $expires,
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $message = (string) $request['message'];

            return str_contains($message, 'প্ল্যানটি Renew')
                && ! str_contains($message, 'লাইসেন্স')
                && ! str_contains($message, 'http');
        });
        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'subscription_expiring',
            'channel' => 'sms',
        ]);
        $this->assertDatabaseMissing('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'license_expiring',
            'channel' => 'sms',
        ]);
    }

    public function test_quota_alerts_do_not_send_sms_when_only_expiry_sms_is_enabled(): void
    {
        Http::fake();
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
            'subscription.notifications.sms_expiry' => true,
        ]);

        [$user] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('subscription_alert_logs', [
            'user_id' => $user->id,
            'channel' => 'sms',
        ]);
    }

    public function test_notify_command_does_not_duplicate_email_same_day(): void
    {
        Mail::fake();
        config([
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => true,
            'subscription.notifications.min_severity' => 'warning',
        ]);

        [$user] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 10,
            'total_order_handled' => 90,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();
        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();

        Mail::assertSent(SubscriptionAlertMail::class, 1);
    }
}
