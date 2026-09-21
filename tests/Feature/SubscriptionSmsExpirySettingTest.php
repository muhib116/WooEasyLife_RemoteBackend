<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\SubscriptionNotificationRuntimeConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubscriptionSmsExpirySettingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Settings Admin',
            'email' => 'settings-admin-'.uniqid().'@example.com',
            'phone' => '01700000088',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        SubscriptionNotificationRuntimeConfig::clearMemoForTests();
    }

    public function test_settings_page_shows_expiry_sms_enabled_by_default(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('landingSettings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LandingSettings/Index')
                ->where('settings.sms_expiry', true)
            );
    }

    public function test_settings_can_disable_expiry_sms(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->put(route('landingSettings.update'), [
                'sms_expiry' => false,
            ])
            ->assertRedirect();

        expect(config('subscription.notifications.sms_expiry'))->toBeFalse()
            ->and(PlatformSetting::query()->where('key', SubscriptionNotificationRuntimeConfig::SETTING_KEY)->exists())->toBeTrue();

        SubscriptionNotificationRuntimeConfig::clearMemoForTests();
        app(SubscriptionNotificationRuntimeConfig::class)->applyOverrides();

        expect(config('subscription.notifications.sms_expiry'))->toBeFalse();
    }

    public function test_disabled_expiry_sms_setting_stops_notify_command(): void
    {
        Http::fake();
        config([
            'services.bulksms.api_key' => 'test-key',
            'services.bulksms.sender_id' => '8809617619992',
            'subscription.notifications.enabled' => true,
            'subscription.notifications.email' => false,
            'subscription.notifications.sms' => false,
        ]);

        app(SubscriptionNotificationRuntimeConfig::class)->update(false);

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'expiry-setting-'.uniqid().'@example.com',
            'phone' => '01711112222',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        AccessToken::unguarded(function () use ($user) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
                'token' => hash('sha256', 'expiry-setting-token'),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

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

        $this->artisan('subscriptions:notify', ['--user-id' => $user->id])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseMissing('subscription_alert_logs', [
            'user_id' => $user->id,
            'channel' => 'sms',
        ]);
    }
}
