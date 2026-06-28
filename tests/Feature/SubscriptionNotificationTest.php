<?php

namespace Tests\Feature;

use App\Mail\SubscriptionAlertMail;
use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
