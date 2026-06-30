<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionAlertApiTest extends TestCase
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

        return [$user, $plainToken];
    }

    private function apiHeaders(string $plainToken, string $origin): array
    {
        return [
            'Authorization' => 'Bearer ' . $plainToken,
            'Origin' => $origin,
        ];
    }

    public function test_get_user_includes_billing_alerts_for_low_quota(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

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

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('billing.alerts.0.type', 'quota_low')
            ->assertJsonStructure([
                'billing' => [
                    'subscription_status',
                    'remaining_order',
                    'expires_at',
                    'pending_payment_count',
                    'has_pending_payment',
                    'pending_payments',
                    'alerts',
                ],
            ]);
    }

    public function test_get_user_notice_includes_quota_exhausted(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

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

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/get-user');

        $response->assertOk();

        $notice = $response->json('notice');
        $this->assertIsArray($notice);
        $this->assertTrue(
            collect($notice)->contains(fn (array $item) => ($item['code'] ?? null) === 'quota_exhausted')
        );
    }

    public function test_check_alerts_command_logs_results(): void
    {
        [$user] = $this->createMerchantWithToken();

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

        $this->artisan('subscriptions:check-alerts', ['--user-id' => $user->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('subscription_alert_logs', [
            'user_id' => $user->id,
            'alert_type' => 'quota_exhausted',
        ]);
    }
}
