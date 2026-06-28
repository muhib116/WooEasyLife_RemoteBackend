<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\PackagePaymentRequest;
use App\Models\SmsBalance;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionAlertServiceTest extends TestCase
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

        $plainToken = 'test-token-' . bin2hex(random_bytes(8));

        $token = AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Test Token',
                'token' => hash('sha256', $plainToken),
                'domain' => $domain,
                'status' => true,
            ]);
        });

        return [$user, $token, $plainToken];
    }

    public function test_collects_quota_exhausted_alert(): void
    {
        [$user, $token] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 10,
            'remaining_order' => 0,
            'total_order_handled' => 10,
            'per_order_rate' => 1,
            'total_cost' => 10,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $alerts = app(SubscriptionAlertService::class)->collectAlerts($user, $token);

        $this->assertTrue(
            collect($alerts)->contains(fn (array $alert) => $alert['type'] === 'quota_exhausted')
        );
    }

    public function test_collects_subscription_expiring_alert(): void
    {
        [$user, $token] = $this->createMerchantWithToken();

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
            'expires_at' => now()->addDays(3),
        ]);

        $alerts = app(SubscriptionAlertService::class)->collectAlerts($user, $token);

        $this->assertTrue(
            collect($alerts)->contains(fn (array $alert) => $alert['type'] === 'subscription_expiring')
        );
    }

    public function test_collects_pending_payment_alert(): void
    {
        [$user, $token] = $this->createMerchantWithToken();

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

        PackagePaymentRequest::create([
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'domain' => 'shop.example.com',
            'order_limit' => 50,
            'total_amount' => 50,
            'transaction_charge' => 0,
            'transaction_method' => 'Bkash',
            'status' => 'pending',
        ]);

        $alerts = app(SubscriptionAlertService::class)->collectAlerts($user, $token);

        $this->assertTrue(
            collect($alerts)->contains(fn (array $alert) => $alert['type'] === 'payment_pending')
        );
    }

    public function test_logs_alert_with_daily_dedup_key(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $service = app(SubscriptionAlertService::class);
        $alert = [
            'type' => 'quota_low',
            'severity' => 'warning',
            'message' => 'Low quota',
        ];

        $service->logAlert($user, 'shop.example.com', $alert);
        $service->logAlert($user, 'shop.example.com', $alert);

        $this->assertDatabaseCount('subscription_alert_logs', 1);
        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'quota_low',
        ]);
    }

    public function test_plugin_notices_include_sms_low_when_sent_before(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        [$user, $token] = $this->createMerchantWithToken();

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

        SmsBalance::create([
            'user_id' => $user->id,
            'domain' => 'shop.example.com',
            'amount' => 10,
            'type' => 'out',
            'sms_count' => 1,
        ]);

        $notices = app(SubscriptionAlertService::class)->pluginNotices($user, $token);

        $this->assertNotNull($notices);
        $this->assertTrue(
            collect($notices)->contains(fn (array $notice) => ($notice['code'] ?? null) === 'sms_low')
        );
    }
}
