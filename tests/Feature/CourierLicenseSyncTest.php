<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use App\Models\Website;
use App\Services\Courier\CourierLicenseSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierLicenseSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchant(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.com',
            'phone' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);
    }

    private function addWebsite(User $user, string $domain, bool $primary): array
    {
        $website = Website::create([
            'user_id' => $user->id,
            'title' => $domain,
            'domain' => $domain,
            'base_url' => 'https://'.$domain,
            'status' => true,
            'is_primary' => $primary,
        ]);

        $plain = 'token-'.$domain.'-'.bin2hex(random_bytes(8));

        $token = AccessToken::unguarded(function () use ($user, $website, $domain, $plain) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => $domain,
                'token' => hash('sha256', $plain),
                'domain' => $domain,
                'website_id' => $website->id,
                'status' => true,
            ]);
        });

        return [$plain, $token];
    }

    private function headers(string $plain, string $domain): array
    {
        return [
            'Authorization' => 'Bearer '.$plain,
            'Origin' => 'https://'.$domain,
        ];
    }

    public function test_a_new_second_website_does_not_receive_the_primary_courier_configuration(): void
    {
        $user = $this->createMerchant('Two Stores');
        [$primaryToken] = $this->addWebsite($user, 'primary-store.test', true);
        [$secondToken] = $this->addWebsite($user, 'second-store.test', false);

        $this->withHeaders($this->headers($primaryToken, 'primary-store.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'primary-api-key',
                'secret_key' => 'primary-secret',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->withHeaders($this->headers($secondToken, 'second-store.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.can_sync', true)
            ->assertJsonPath('data.steadfast.synced', false)
            ->assertJsonPath('data.steadfast.is_primary_website', false)
            ->assertJsonPath('data.steadfast.has_other_sites', true)
            ->assertJsonPath('data.steadfast.sync_source_domain', 'primary-store.test')
            ->assertJsonMissingPath('data.steadfast.api_key');

        $this->withHeaders($this->headers($primaryToken, 'primary-store.test'))
            ->getJson('/api/get-user')
            ->assertOk()
            ->assertJsonPath('site.is_primary', true)
            ->assertJsonPath('site.has_other_sites', true)
            ->assertJsonPath('site.primary_domain', 'primary-store.test');

        $this->withHeaders($this->headers($secondToken, 'second-store.test'))
            ->getJson('/api/get-user')
            ->assertOk()
            ->assertJsonPath('site.is_primary', false)
            ->assertJsonPath('site.has_other_sites', true)
            ->assertJsonPath('site.primary_domain', 'primary-store.test');

        Http::fake();

        $this->withHeaders($this->headers($secondToken, 'second-store.test'))
            ->postJson('/api/steadfast/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'INV1',
                    'recipient_name' => 'Karim',
                    'recipient_phone' => '01711111111',
                    'recipient_address' => 'Dhaka Bangladesh address line',
                    'cod_amount' => 100,
                ]],
            ])
            ->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_sync_uses_primary_credentials_without_copying_them_onto_a_second_row(): void
    {
        $user = $this->createMerchant('Sync Stores');
        [$primaryPlain] = $this->addWebsite($user, 'primary-sync.test', true);
        [$secondPlain] = $this->addWebsite($user, 'second-sync.test', false);

        $this->withHeaders($this->headers($primaryPlain, 'primary-sync.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'primary-api-key',
                'secret_key' => 'primary-secret',
                'is_active' => true,
            ])
            ->assertOk();

        Http::fake([
            'https://portal.packzy.com/api/v1/create_order/bulk-order' => Http::response([
                'status' => 200,
                'data' => [[
                    'consignment_id' => 'C-9',
                    'invoice' => 'INV9',
                    'status' => 'in_review',
                ]],
            ], 200),
        ]);

        $this->withHeaders($this->headers($secondPlain, 'second-sync.test'))
            ->postJson('/api/courier/sync-configuration', ['slug' => 'steadfast'])
            ->assertOk()
            ->assertJsonPath('data.synced', true)
            ->assertJsonPath('data.api_key', 'primary-api-key')
            ->assertJsonPath('data.sync_source_domain', 'primary-sync.test')
            ->assertJsonMissingPath('data.id');

        $this->assertSame(1, CourierConfiguration::query()->where('user_id', $user->id)->where('slug', 'steadfast')->count());

        $this->withHeaders($this->headers($secondPlain, 'second-sync.test'))
            ->postJson('/api/steadfast/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'INV9',
                    'recipient_name' => 'Karim',
                    'recipient_phone' => '01711111111',
                    'recipient_address' => 'Dhaka Bangladesh address line',
                    'cod_amount' => 80,
                ]],
            ])
            ->assertOk();

        Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'primary-api-key'));
    }

    public function test_saving_separate_credentials_stops_sync_and_leaves_the_primary_key_unchanged(): void
    {
        $user = $this->createMerchant('Own Keys');
        [$primaryPlain] = $this->addWebsite($user, 'primary-own.test', true);
        [$secondPlain] = $this->addWebsite($user, 'second-own.test', false);

        $this->withHeaders($this->headers($primaryPlain, 'primary-own.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'primary-api-key',
                'secret_key' => 'primary-secret',
                'is_active' => true,
            ])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-own.test'))
            ->postJson('/api/courier/sync-configuration', ['slug' => 'steadfast'])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-own.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'second-api-key',
                'secret_key' => 'second-secret',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.synced', false)
            ->assertJsonPath('data.api_key', 'second-api-key');

        $primary = CourierConfiguration::query()
            ->where('user_id', $user->id)
            ->where('slug', 'steadfast')
            ->where('api_key', 'primary-api-key')
            ->first();

        $this->assertNotNull($primary);
        $this->assertSame('primary-secret', $primary->secret_key);

        $this->withHeaders($this->headers($primaryPlain, 'primary-own.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.api_key', 'primary-api-key')
            ->assertJsonPath('data.steadfast.is_primary_website', true)
            ->assertJsonPath('data.steadfast.has_other_sites', true);
    }

    public function test_detach_keeps_separate_credentials_even_when_the_api_key_matches(): void
    {
        $user = $this->createMerchant('Detach Same Key');
        [$primaryPlain] = $this->addWebsite($user, 'primary-detach.test', true);
        [$secondPlain] = $this->addWebsite($user, 'second-detach.test', false);

        $this->withHeaders($this->headers($primaryPlain, 'primary-detach.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
                'settings' => [
                    'username' => 'primary@steadfast.test',
                    'password' => 'primary-pass',
                ],
            ])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-detach.test'))
            ->postJson('/api/courier/sync-configuration', ['slug' => 'steadfast'])
            ->assertOk()
            ->assertJsonPath('data.synced', true);

        $this->withHeaders($this->headers($secondPlain, 'second-detach.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
                'detach' => true,
                'settings' => [
                    'username' => 'second@steadfast.test',
                    'password' => 'second-pass',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.synced', false)
            ->assertJsonPath('data.can_sync', true)
            ->assertJsonPath('data.api_key', 'shared-api-key')
            ->assertJsonPath('data.settings.username', 'second@steadfast.test');

        $this->assertSame(2, CourierConfiguration::query()->where('user_id', $user->id)->where('slug', 'steadfast')->count());

        $primary = CourierConfiguration::query()
            ->where('user_id', $user->id)
            ->where('slug', 'steadfast')
            ->where('api_key', 'shared-api-key')
            ->orderBy('id')
            ->first();
        $this->assertNotNull($primary);
        $this->assertSame('primary@steadfast.test', $primary->settings['username'] ?? null);
        $this->assertSame('primary-pass', $primary->settings['password'] ?? null);

        $this->withHeaders($this->headers($secondPlain, 'second-detach.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.synced', false)
            ->assertJsonPath('data.steadfast.can_sync', true)
            ->assertJsonPath('data.steadfast.settings.username', 'second@steadfast.test');

        $this->withHeaders($this->headers($primaryPlain, 'primary-detach.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.synced', false)
            ->assertJsonPath('data.steadfast.settings.username', 'primary@steadfast.test');
    }

    public function test_portal_login_change_on_a_synced_site_is_stored_as_its_own_row(): void
    {
        $user = $this->createMerchant('Portal Only');
        [$primaryPlain] = $this->addWebsite($user, 'primary-portal.test', true);
        [$secondPlain] = $this->addWebsite($user, 'second-portal.test', false);

        $this->withHeaders($this->headers($primaryPlain, 'primary-portal.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
                'settings' => [
                    'username' => 'primary@steadfast.test',
                ],
            ])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-portal.test'))
            ->postJson('/api/courier/sync-configuration', ['slug' => 'steadfast'])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-portal.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
                'settings' => [
                    'username' => 'second@steadfast.test',
                    'password' => 'second-pass',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.synced', false)
            ->assertJsonPath('data.settings.username', 'second@steadfast.test');

        $primary = CourierConfiguration::query()
            ->where('user_id', $user->id)
            ->where('slug', 'steadfast')
            ->orderBy('id')
            ->first();
        $this->assertSame('primary@steadfast.test', $primary->settings['username'] ?? null);
    }

    public function test_resaving_the_same_shared_keys_stays_synced(): void
    {
        $user = $this->createMerchant('Stay Synced');
        [$primaryPlain] = $this->addWebsite($user, 'primary-stay.test', true);
        [$secondPlain] = $this->addWebsite($user, 'second-stay.test', false);

        $this->withHeaders($this->headers($primaryPlain, 'primary-stay.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
            ])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-stay.test'))
            ->postJson('/api/courier/sync-configuration', ['slug' => 'steadfast'])
            ->assertOk();

        $this->withHeaders($this->headers($secondPlain, 'second-stay.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'shared-api-key',
                'secret_key' => 'shared-secret',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.synced', true)
            ->assertJsonMissingPath('data.id');

        $this->assertSame(1, CourierConfiguration::query()->where('user_id', $user->id)->where('slug', 'steadfast')->count());
    }

    public function test_existing_second_website_stays_synced_after_backfill(): void
    {
        $user = $this->createMerchant('Grandfather');
        [$primaryPlain] = $this->addWebsite($user, 'old-primary.test', true);
        [$secondPlain] = $this->addWebsite($user, 'old-second.test', false);

        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'shared-api-key',
            'secret_key' => 'shared-secret',
            'is_active' => true,
        ]);

        app(CourierLicenseSyncService::class)->backfillExistingLinks();

        $this->withHeaders($this->headers($secondPlain, 'old-second.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.synced', true)
            ->assertJsonPath('data.steadfast.api_key', 'shared-api-key');

        $this->withHeaders($this->headers($primaryPlain, 'old-primary.test'))
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.synced', false)
            ->assertJsonPath('data.steadfast.api_key', 'shared-api-key')
            ->assertJsonPath('data.steadfast.can_sync', false)
            ->assertJsonPath('data.steadfast.is_primary_website', true)
            ->assertJsonPath('data.steadfast.has_other_sites', true);
    }

    public function test_steadfast_ping_reports_a_reachable_api_without_sending_merchant_keys(): void
    {
        $user = $this->createMerchant('Ping Store');
        [$plain] = $this->addWebsite($user, 'ping-store.test', true);

        Http::fake([
            'https://portal.packzy.com/api/v1/ping' => Http::response([
                'status' => 200,
                'message' => 'Welcome',
                'time' => '2026-10-22T11:37:00+06:00',
            ], 200),
        ]);

        $this->withHeaders($this->headers($plain, 'ping-store.test'))
            ->postJson('/api/steadfast/ping')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reachable', true)
            ->assertJsonPath('data.http_status', 200)
            ->assertJsonPath('message', 'Steadfast API is reachable.');

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://portal.packzy.com/api/v1/ping'
                && ! $request->hasHeader('Api-Key')
                && ! $request->hasHeader('Secret-Key');
        });
    }

    public function test_steadfast_ping_reports_their_http_500(): void
    {
        $user = $this->createMerchant('Ping Down');
        [$plain] = $this->addWebsite($user, 'ping-down.test', true);

        Http::fake([
            'https://portal.packzy.com/api/v1/ping' => Http::response([
                'message' => 'Server Error',
            ], 500),
        ]);

        $this->withHeaders($this->headers($plain, 'ping-down.test'))
            ->postJson('/api/steadfast/ping')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.reachable', false)
            ->assertJsonPath('data.http_status', 500)
            ->assertJsonPath('message', 'Steadfast API returned HTTP 500.');
    }
}
