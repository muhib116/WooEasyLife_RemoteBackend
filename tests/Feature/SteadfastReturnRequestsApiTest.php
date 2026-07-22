<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteadfastReturnRequestsApiTest extends TestCase
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
            'features' => PackageCatalogFeatures::normalize($features),
            'is_active' => true,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);
    }

    private function attachSteadfastConfig(User $user): void
    {
        CourierConfiguration::create([
            'user_id' => $user->id,
            'slug' => 'steadfast',
            'api_key' => 'sf-api-key',
            'secret_key' => 'sf-secret',
            'settings' => [
                'username' => 'merchant@steadfast.test',
                'password' => 'secret-password',
            ],
            'is_active' => true,
        ]);
    }

    public function test_create_return_request_requires_courier_automation(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => false,
        ]);
        $this->attachSteadfastConfig($user);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests/create', [
                'consignment_id' => '272300623',
                'reason' => 'Customer cancelled the order after booking',
            ])
            ->assertStatus(403);
    }

    public function test_create_return_request_via_packzy_api(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        Http::fake([
            'portal.packzy.com/api/v1/create_return_request' => Http::response([
                'status' => 200,
                'data' => [
                    'id' => 99,
                    'consignment_id' => 272300623,
                    'reason' => 'Customer cancelled the order after booking',
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests/create', [
                'consignment_id' => '272300623',
                'reason' => 'Customer cancelled the order after booking',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.consignment_id', '272300623')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_list_return_requests_via_packzy_when_portal_missing(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);

        CourierConfiguration::create([
            'user_id' => $user->id,
            'slug' => 'steadfast',
            'api_key' => 'sf-api-key',
            'secret_key' => 'sf-secret',
            'settings' => [],
            'is_active' => true,
        ]);

        Http::fake([
            'portal.packzy.com/api/v1/get_return_requests' => Http::response([
                'status' => 200,
                'data' => [
                    [
                        'id' => 1,
                        'consignment_id' => 272300623,
                        'status' => 'pending',
                        'reason' => 'demo',
                        'customer_name' => 'Nirob Hasan',
                        'cod_amount' => 820,
                    ],
                ],
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'status' => 'pending',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.consignment_id', '272300623')
            ->assertJsonPath('data.counts.pending', 1);
    }

    public function test_update_return_status_requires_portal_credentials(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);

        CourierConfiguration::create([
            'user_id' => $user->id,
            'slug' => 'steadfast',
            'api_key' => 'sf-api-key',
            'secret_key' => 'sf-secret',
            'settings' => [],
            'is_active' => true,
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests/update-status', [
                'action' => 'confirm_cancel',
                'consignment_id' => '272300623',
            ])
            ->assertStatus(422);
    }

    public function test_update_return_status_requires_consignment_id(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests/update-status', [
                'action' => 'confirm_cancel',
                'id' => '99',
            ])
            ->assertStatus(422);
    }

    public function test_list_return_requests_uses_packzy_even_with_portal_credentials(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        Http::fake([
            'portal.packzy.com/api/v1/get_return_requests' => Http::response([
                'status' => 200,
                'data' => [
                    [
                        'id' => 42,
                        'consignment_id' => 272300623,
                        'status' => 'pending',
                        'reason' => 'from-packzy',
                        'customer_name' => 'Packzy Customer',
                    ],
                ],
            ], 200),
            'steadfast.com/*' => Http::response('login', 200),
            'scstech.io/*' => Http::response('login', 200),
        ]);

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'status' => 'pending',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.consignment_id', '272300623')
            ->assertJsonPath('data.items.0.reason', 'from-packzy')
            ->assertJsonPath('data.counts.pending', 1);
    }
}
