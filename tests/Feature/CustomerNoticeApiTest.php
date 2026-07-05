<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CustomerNotice;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\CustomerNoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerNoticeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CustomerNoticeService::class)->forgetCache();
    }

    private function createMerchantWithToken(
        string $domain = 'shop.example.com',
        ?string $plainToken = null
    ): array {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = $plainToken ?? 'test-token-' . bin2hex(random_bytes(16));

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

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $plainToken, ?string $origin = 'https://shop.example.com'): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $plainToken,
        ];

        if ($origin !== null) {
            $headers['Origin'] = $origin;
        }

        return $headers;
    }

    public function test_notices_api_returns_audience_filtered_notices_for_active_subscriber(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Starter',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 0,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
        ]);

        $forAll = CustomerNotice::create([
            'title' => 'For everyone',
            'body' => 'General announcement',
            'type' => 'general',
            'severity' => 'info',
            'audience' => 'all',
            'is_active' => true,
        ]);

        CustomerNotice::create([
            'title' => 'Active only',
            'body' => 'Subscriber offer',
            'type' => 'offer',
            'severity' => 'success',
            'audience' => 'active_subscribers',
            'is_active' => true,
        ]);

        CustomerNotice::create([
            'title' => 'Expired only',
            'body' => 'Win back',
            'type' => 'offer',
            'severity' => 'warning',
            'audience' => 'recent_expired',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/notices');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $forAll->id,
                'title' => 'For everyone',
            ])
            ->assertJsonFragment([
                'title' => 'Active only',
            ])
            ->assertJsonMissing([
                'title' => 'Expired only',
            ]);
    }

    public function test_notices_api_excludes_inactive_and_scheduled_notices(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        CustomerNotice::create([
            'title' => 'Live notice',
            'body' => 'Visible now',
            'type' => 'general',
            'severity' => 'info',
            'audience' => 'all',
            'is_active' => true,
        ]);

        CustomerNotice::create([
            'title' => 'Disabled notice',
            'body' => 'Hidden',
            'type' => 'general',
            'severity' => 'info',
            'audience' => 'all',
            'is_active' => false,
        ]);

        CustomerNotice::create([
            'title' => 'Future notice',
            'body' => 'Scheduled',
            'type' => 'general',
            'severity' => 'info',
            'audience' => 'all',
            'is_active' => true,
            'starts_at' => now()->addDay(),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/notices');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Live notice');
    }

    public function test_notices_api_requires_valid_token(): void
    {
        $response = $this->withHeaders($this->apiHeaders('invalid-token'))
            ->getJson('/api/notices');

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid Token');
    }

    public function test_notices_api_requires_origin_header(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, null))
            ->getJson('/api/notices');

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Origin domain missing from header');
    }

    public function test_admin_notice_changes_are_visible_after_cache_is_cleared(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $notice = CustomerNotice::create([
            'title' => 'Original',
            'body' => 'First version',
            'type' => 'general',
            'severity' => 'info',
            'audience' => 'all',
            'is_active' => true,
        ]);

        $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/notices')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Original');

        $notice->update(['title' => 'Updated title']);
        app(\App\Services\CustomerNoticeService::class)->forgetCache();

        $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/notices')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Updated title');
    }
}
