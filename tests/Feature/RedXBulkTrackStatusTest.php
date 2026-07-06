<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RedXBulkTrackStatusTest extends TestCase
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

    public function test_redx_bulk_track_status_returns_statuses_over_http(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'RedX',
            'slug' => 'redx',
            'api_key' => 'redx',
            'secret_key' => 'test-redx-token',
            'is_active' => true,
            'settings' => [
                'environment' => 'sandbox',
            ],
        ]);

        Http::fake([
            'https://sandbox.redx.com.bd/v1.0.0-beta/parcel/info/RX-TEST' => Http::response([
                'parcel' => [
                    'status' => 'dispatched',
                ],
            ]),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/redx/bulk-track-status', [
                'consignment_ids' => ['RX-TEST'],
                'environment' => 'sandbox',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.RX-TEST', 'dispatched');
    }

    public function test_redx_bulk_track_status_requires_tracking_ids(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'RedX',
            'slug' => 'redx',
            'api_key' => 'redx',
            'secret_key' => 'test-redx-token',
            'is_active' => true,
            'settings' => [
                'environment' => 'sandbox',
            ],
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/redx/bulk-track-status', [
                'consignment_ids' => [],
            ]);

        $response->assertStatus(422);
    }
}
