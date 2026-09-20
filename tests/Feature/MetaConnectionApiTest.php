<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\MetaAdAccount;
use App\Models\MetaConnection;
use App\Models\MetaPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * @group meta
 */
class MetaConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    private function createLicense(string $plainToken = 'meta-license-test-token'): string
    {
        $user = User::create([
            'name' => 'Meta Merchant',
            'email' => 'meta-merchant-' . uniqid() . '@example.com',
            'phone' => '017' . random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        AccessToken::unguarded(function () use ($user, $plainToken) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Meta Test',
                'token' => hash('sha256', $plainToken),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        return $plainToken;
    }

    public function test_connect_stores_ads_and_pages_with_encrypted_tokens(): void
    {
        $license = $this->createLicense();

        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'act_111',
                        'account_id' => '111',
                        'name' => 'Ads One',
                        'currency' => 'BDT',
                    ],
                ],
            ], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'data' => [
                    [
                        'id' => 'page_222',
                        'name' => 'Shop Page',
                        'access_token' => 'PAGE_TOKEN_SECRET',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/meta/connect', [
            'license_key' => $license,
            'access_token' => 'SYSTEM_USER_TOKEN_SECRET',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.accounts.0.account_id', 'act_111')
            ->assertJsonPath('data.pages.0.page_id', 'page_222')
            ->assertJsonMissingPath('data.pages.0.page_access_token');

        $connection = MetaConnection::query()->where('license_key', $license)->first();
        $this->assertNotNull($connection);
        $this->assertSame('SYSTEM_USER_TOKEN_SECRET', $connection->system_user_token);
        $this->assertNotSame(
            'SYSTEM_USER_TOKEN_SECRET',
            $connection->getAttributes()['system_user_token'] ?? null
        );
        $this->assertContains('ads_read', $connection->granted_scopes);
        $this->assertContains('pages_messaging', $connection->granted_scopes);

        $page = MetaPage::query()->where('page_id', 'page_222')->first();
        $this->assertNotNull($page);
        $this->assertSame('PAGE_TOKEN_SECRET', $page->page_access_token);
        $this->assertNotSame(
            'PAGE_TOKEN_SECRET',
            $page->getAttributes()['page_access_token'] ?? null
        );

        // Tokens must not appear in JSON response bodies.
        $raw = $response->getContent();
        $this->assertStringNotContainsString('SYSTEM_USER_TOKEN_SECRET', $raw);
        $this->assertStringNotContainsString('PAGE_TOKEN_SECRET', $raw);
    }

    public function test_connect_succeeds_with_ads_only(): void
    {
        $license = $this->createLicense('ads-only-license');

        Http::fake([
            'graph.facebook.com/*/me/adaccounts*' => Http::response([
                'data' => [
                    ['id' => 'act_9', 'account_id' => '9', 'name' => 'Only Ads', 'currency' => 'USD'],
                ],
            ], 200),
            'graph.facebook.com/*/me/accounts*' => Http::response([
                'error' => ['message' => '(#200) Requires pages permission'],
            ], 400),
        ]);

        $this->postJson('/api/v1/meta/connect', [
            'license_key' => $license,
            'access_token' => 'ADS_ONLY_TOKEN',
        ])->assertOk()
            ->assertJsonPath('data.accounts.0.account_id', 'act_9')
            ->assertJsonPath('data.pages', []);

        $connection = MetaConnection::query()->where('license_key', $license)->first();
        $this->assertContains('ads_read', $connection->granted_scopes);
        $this->assertNotContains('pages_messaging', $connection->granted_scopes);
    }

    public function test_connect_rejects_invalid_license_without_calling_meta(): void
    {
        Http::fake();

        $this->postJson('/api/v1/meta/connect', [
            'license_key' => 'not-a-real-license',
            'access_token' => 'ANY',
        ])->assertStatus(422)
            ->assertJsonPath('status', false);

        Http::assertNothingSent();
        $this->assertSame(0, MetaConnection::query()->count());
    }

    public function test_accounts_and_pages_list_endpoints(): void
    {
        $license = $this->createLicense('list-license');

        $connection = MetaConnection::query()->create([
            'license_key' => $license,
            'system_user_token' => 'stored-token',
            'granted_scopes' => ['ads_read', 'pages_messaging'],
            'status' => 'active',
            'source' => 'system_user',
            'last_verified_at' => now(),
        ]);

        MetaAdAccount::query()->create([
            'meta_connection_id' => $connection->id,
            'account_id' => 'act_1',
            'account_name' => 'A',
            'currency' => 'BDT',
            'is_tracked' => true,
        ]);

        MetaPage::query()->create([
            'meta_connection_id' => $connection->id,
            'page_id' => 'p1',
            'page_name' => 'Page',
            'page_access_token' => 'page-tok',
            'is_tracked' => false,
            'webhook_subscribed' => false,
        ]);

        $this->getJson('/api/v1/meta/accounts?license_key=' . urlencode($license))
            ->assertOk()
            ->assertJsonPath('data.accounts.0.account_id', 'act_1')
            ->assertJsonPath('data.accounts.0.is_tracked', true);

        $this->getJson('/api/v1/meta/pages?license_key=' . urlencode($license))
            ->assertOk()
            ->assertJsonPath('data.pages.0.page_id', 'p1')
            ->assertJsonMissingPath('data.pages.0.page_access_token');
    }

    public function test_connect_does_not_leak_raw_meta_errors(): void
    {
        $license = $this->createLicense('bad-token-license');

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token SECRET_LEAK',
                    'type' => 'OAuthException',
                    'code' => 190,
                ],
            ], 400),
        ]);

        $response = $this->postJson('/api/v1/meta/connect', [
            'license_key' => $license,
            'access_token' => 'BAD_TOKEN',
        ]);

        $response->assertStatus(422);
        $raw = $response->getContent();
        $this->assertStringNotContainsString('SECRET_LEAK', $raw);
        $this->assertStringNotContainsString('OAuthException', $raw);
        $this->assertStringNotContainsString('BAD_TOKEN', $raw);
    }
}
