<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Courier\SteadfastNotificationsService;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SteadfastNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchantWithToken(string $domain = 'shop.example.com'): array
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-'.uniqid().'@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'test-token-'.bin2hex(random_bytes(16));

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

    private function apiHeaders(string $plainToken, string $origin = 'https://shop.example.com'): array
    {
        return [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => $origin,
        ];
    }

    private function attachCatalogPackage(User $user, array $features, string $domain = 'shop.example.com'): UserPackage
    {
        $plan = PackageHub::create([
            'title' => 'Courier Plan',
            'description' => 'Test',
            'per_order_rate' => 0,
            'package_price' => 999,
            'order_rate_token' => 500,
            'package_duration' => '1_month',
            'is_active' => true,
            'index' => 1,
            'features' => PackageCatalogFeatures::normalize($features),
        ]);

        return UserPackage::create([
            'title' => $plan->title,
            'domain' => $domain,
            'user_id' => $user->id,
            'package_hub_id' => $plan->id,
            'plan_type' => 'catalog',
            'total_order_can_handle' => 500,
            'remaining_order' => 400,
            'total_order_handled' => 100,
            'per_order_rate' => 0,
            'total_cost' => 999,
            'transaction_charge' => 0,
            'is_active' => true,
            'features' => PackageCatalogFeatures::normalize($features),
        ]);
    }

    private function attachSteadfastPortalCredentials(User $user): void
    {
        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'api-key',
            'secret_key' => 'secret-key',
            'is_active' => true,
            'settings' => [
                'username' => 'merchant@steadfast.test',
                'password' => 'portal-password',
            ],
        ]);
    }

    public function test_notifications_requires_courier_automation(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => false,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/notifications', []);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Courier automation is not included in your current plan.');
    }

    public function test_notifications_requires_portal_credentials(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/notifications', []);

        $response->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Steadfast portal username/password are not configured. Add them in Config → Courier → Steadfast.'
            );
    }

    public function test_notifications_accepts_request_credentials_override(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        // No hub CourierConfiguration — credentials come from the request body.

        $this->mock(SteadfastNotificationsService::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->withArgs(function (array $credentials, $cursor) {
                    return ($credentials['username'] ?? '') === 'merchant@steadfast.test'
                        && ($credentials['password'] ?? '') === 'portal-password'
                        && $cursor === null;
                })
                ->andReturn([
                    'items' => [
                        [
                            'message' => 'Parcel #273894578 has been delivered.',
                            'consignment_id' => '273894578',
                            'url' => 'https://www.steadfast.com.bd/user/consignment/273894578',
                            'relative_time' => '42 minutes ago',
                            'is_read' => true,
                        ],
                    ],
                    'next_cursor' => null,
                    'has_more' => false,
                    'unread_count' => 0,
                ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/notifications', [
                'username' => 'merchant@steadfast.test',
                'password' => 'portal-password',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.consignment_id', '273894578');
    }
}
