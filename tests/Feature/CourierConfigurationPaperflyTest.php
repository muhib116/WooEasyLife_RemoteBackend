<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierAccount;
use App\Models\CourierConfiguration;
use App\Models\CourierLicenseLink;
use App\Models\CourierShipment;
use App\Models\CourierWebhookEvent;
use App\Models\User;
use App\Services\Courier\CourierLicenseSyncService;
use App\Services\Courier\PaperflyCourierService;
use App\Services\Courier\WordPressCourierForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierConfigurationPaperflyTest extends TestCase
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

    private function apiHeaders(string $plainToken, string $origin): array
    {
        return [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => $origin,
        ];
    }

    private function savePaperfly(string $plainToken, string $origin, array $overrides = [])
    {
        return $this->withHeaders($this->apiHeaders($plainToken, $origin))
            ->postJson('/api/courier/save-configuration', array_replace_recursive([
                'slug' => 'paperfly',
                'api_key' => 'panel-user',
                'secret_key' => 'panel-pass',
                'is_active' => false,
                'settings' => [
                    'username' => 'panel-user',
                    'store_name' => '',
                    'max_weight' => '',
                    'webhook_secret' => 'hook-secret',
                ],
            ], $overrides));
    }

    public function test_courier_list_includes_paperfly_after_redx(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/list');

        $response->assertOk()->assertJsonPath('status', true);

        $slugs = array_column($response->json('data'), 'slug');
        $this->assertSame(['steadfast', 'pathao', 'redx', 'paperfly'], $slugs);
        $this->assertNotNull($user->id);
    }

    public function test_save_blanks_password_keeps_it_on_update_and_stores_weight_as_typed(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        $created = $this->savePaperfly($plainToken, 'https://shop.example.com', [
            'is_active' => false,
            'settings' => [
                'store_name' => 'Chander Alo',
                'max_weight' => '0.3',
            ],
        ]);

        $created->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Paperfly')
            ->assertJsonPath('data.secret_key', '')
            ->assertJsonPath('data.settings.store_name', '')
            ->assertJsonPath('data.settings.default_weight', '0.3')
            ->assertJsonPath('data.settings.webhook_secret', 'hook-secret');

        $configuration = CourierConfiguration::query()
            ->where('user_id', $user->id)
            ->where('slug', 'paperfly')
            ->first();

        $this->assertNotNull($configuration);
        $this->assertSame('panel-pass', $configuration->secret_key);
        $this->assertArrayNotHasKey('password', $configuration->settings ?? []);

        $active = [
            'id' => '10',
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'email' => 'store@example.com',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address_line' => 'House 12, Road 5',
            'label' => 'Main',
        ];
        $meta = ['10' => ['email' => 'store@example.com', 'label' => 'Main']];
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'active_store' => $active,
            'store_meta' => $meta,
            'store_name' => 'Chander Alo',
        ]);
        $configuration->save();

        $updated = $this->savePaperfly($plainToken, 'https://shop.example.com', [
            'id' => $configuration->id,
            'secret_key' => '',
            'is_active' => true,
            'settings' => [
                'username' => 'panel-user',
                'store_name' => 'Chander Alo Gallery',
                'active_store' => ['id' => '99', 'full_name' => 'Posted object'],
                'store_meta' => ['99' => ['email' => 'posted@example.com', 'label' => 'Posted']],
                'default_weight' => '1.0',
                'webhook_secret' => '',
            ],
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.secret_key', '')
            ->assertJsonPath('data.has_saved_password', true);

        $configuration->refresh();
        $this->assertSame('panel-pass', $configuration->secret_key);
        $this->assertSame('Chander Alo', $configuration->settings['store_name']);
        $this->assertSame('hook-secret', $configuration->settings['webhook_secret']);
        $this->assertEquals($active, $configuration->settings['active_store']);
        $this->assertEquals($meta, $configuration->settings['store_meta']);
        $this->assertSame('1.0', $configuration->settings['default_weight']);

        $loaded = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/courier/get-configuration');

        $loaded->assertOk()
            ->assertJsonPath('data.paperfly.secret_key', '')
            ->assertJsonPath('data.paperfly.has_saved_password', true)
            ->assertJsonPath('data.paperfly.settings.store_name', 'Chander Alo')
            ->assertJsonPath('data.paperfly.settings.active_store.id', '10');

        app(CourierLicenseSyncService::class)->backfillExistingLinks();

        $this->assertTrue(
            CourierLicenseLink::query()
                ->where('user_id', $user->id)
                ->where('slug', 'paperfly')
                ->exists()
        );
    }

    public function test_store_name_and_weight_rules(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $headers = $this->apiHeaders($plainToken, 'https://shop.example.com');

        $this->withHeaders($headers)->postJson('/api/courier/save-configuration', [
            'slug' => 'paperfly',
            'api_key' => 'panel-user',
            'secret_key' => 'panel-pass',
            'is_active' => true,
            'settings' => [
                'username' => 'panel-user',
                'store_name' => '',
            ],
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/courier/save-configuration', [
            'slug' => 'paperfly',
            'api_key' => 'panel-user',
            'secret_key' => 'panel-pass',
            'is_active' => false,
            'settings' => [
                'username' => 'panel-user',
                'max_weight' => 'abc',
            ],
        ])->assertStatus(422);

        $this->withHeaders($headers)->postJson('/api/courier/save-configuration', [
            'slug' => 'paperfly',
            'api_key' => 'panel-user',
            'secret_key' => 'panel-pass',
            'is_active' => false,
            'settings' => [
                'username' => 'panel-user',
                'max_weight' => '0',
            ],
        ])->assertStatus(422);
    }

    public function test_login_check_uses_sender_list_and_does_not_call_fraud_login(): void
    {
        [, $tokenA] = $this->createMerchantWithToken('shop-a.example.com');
        [, $tokenB] = $this->createMerchantWithToken('shop-b.example.com');

        $this->savePaperfly($tokenA, 'https://shop-a.example.com', [
            'api_key' => 'shop-a',
            'secret_key' => 'pass-a',
            'settings' => ['username' => 'shop-a'],
        ])->assertOk();

        $this->savePaperfly($tokenB, 'https://shop-b.example.com', [
            'api_key' => 'shop-b',
            'secret_key' => 'pass-b',
            'settings' => ['username' => 'shop-b'],
        ])->assertOk();

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'login_using_password.php')
                || str_contains($request->url(), 'new_order')
                || str_contains($request->url(), 'API-Order-Tracking')) {
                return Http::response(['token' => 'should-not-run'], 200);
            }

            if (str_contains($request->url(), 'address/sender/list.php') && str_contains($request->url(), 'limit=1')) {
                return Http::response(['records' => [], 'totalRecords' => 0], 200);
            }

            return Http::response(['unexpected' => true], 500);
        });

        $this->withHeaders($this->apiHeaders($tokenB, 'https://shop-b.example.com'))
            ->postJson('/api/paperfly/test-connection', [
                'username' => 'typed-user',
                'password' => 'typed-pass',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        Http::assertSent(function ($request) {
            $authorization = $request->header('Authorization');
            $authorization = is_array($authorization) ? ($authorization[0] ?? '') : (string) $authorization;
            $key = $request->header('paperflykey');
            $key = is_array($key) ? ($key[0] ?? '') : (string) $key;

            return str_contains($request->url(), 'address/sender/list.php')
                && str_contains($request->url(), 'limit=1')
                && $authorization === 'Basic '.base64_encode('typed-user:typed-pass')
                && $key === PaperflyCourierService::PAPERFLY_KEY
                && ($request->data()['ReferenceNumber'] ?? null) === null;
        });

        $saved = CourierConfiguration::query()->where('api_key', 'shop-b')->first();
        $this->assertSame('pass-b', $saved->secret_key);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'login_using_password.php'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'new_order'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'API-Order-Tracking'));
    }

    public function test_login_check_reports_rejected_login(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();

        Http::fake([
            PaperflyCourierService::SENDER_BASE.'list.php?limit=1&page=1' => Http::response(['message' => 'no'], 401),
        ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/test-connection')
            ->assertStatus(400)
            ->assertJsonPath('status', false);
    }

    public function test_webhook_rejects_a_missing_secret(): void
    {
        $this->postJson('/api/webhooks/paperfly', [
            'event' => 'parcel.delivered',
            'data' => [
                'order_number' => 'Z-051125-63821',
                'merchant_order_reference' => 'LOC1-44',
            ],
        ])->assertStatus(401);

        $this->assertSame(0, CourierWebhookEvent::query()->where('partner', 'paperfly')->count());
    }

    public function test_webhook_forwards_a_delivered_event_when_the_secret_matches(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();

        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $account = CourierAccount::query()->where('partner', 'paperfly')->first();
        $token = AccessToken::query()->where('tokenable_id', $user->id)->first();

        CourierShipment::query()->create([
            'partner' => 'paperfly',
            'environment' => 'live',
            'consignment_id' => 'Z-051125-63821',
            'invoice' => 'LOC1-44',
            'wc_order_id' => 44,
            'user_id' => $user->id,
            'access_token_id' => $token->id,
            'site_url' => 'https://shop.example.com',
            'site_domain' => 'shop.example.com',
            'courier_account_id' => $account->id,
            'courier_configuration_id' => $configuration->id,
            'status' => 'pending',
        ]);

        $this->mock(WordPressCourierForwarder::class, function ($mock) {
            $mock->shouldReceive('forward')
                ->once()
                ->withArgs(function ($shipment, $payload) {
                    return $payload['partner'] === 'paperfly'
                        && $payload['raw_status'] === 'delivered'
                        && $payload['notification_type'] === 'delivery_status'
                        && $payload['consignment_id'] === 'Z-051125-63821'
                        && (int) $payload['order_id'] === 44
                        && (int) $shipment->wc_order_id === 44;
                })
                ->andReturn(['success' => true, 'message' => 'forwarded']);
            $mock->shouldReceive('probeReachability')->andReturn([]);
        });

        $this->withHeaders(['X-Webhook-Secret' => 'hook-secret'])
            ->postJson('/api/webhooks/paperfly', [
                'event' => 'parcel.delivered',
                'timestamp' => '2026-10-30T06:00:00+06:00',
                'data' => [
                    'order_number' => 'Z-051125-63821',
                    'merchant_order_reference' => 'LOC1-44',
                    'package_price' => 850,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $event = CourierWebhookEvent::query()->where('partner', 'paperfly')->first();
        $this->assertNotNull($event);
        $this->assertSame('success', $event->forward_status);
        $this->assertSame('Z-051125-63821', $event->consignment_id);
        $this->assertSame('delivered', CourierShipment::query()->first()->status);
    }

    public function test_webhook_settings_return_a_live_callback_that_is_not_linked(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/courier/webhook-settings?partner=paperfly&environment=sandbox');

        $response->assertOk()
            ->assertJsonPath('data.environment', 'live')
            ->assertJsonPath('data.hub_ready', false)
            ->assertJsonPath('data.action_required', true)
            ->assertJsonPath('data.webhook_auth.type', 'header_secret');

        $this->assertStringContainsString('/api/webhooks/paperfly', (string) $response->json('data.callback_url'));
        $this->assertStringNotContainsString('/sandbox', (string) $response->json('data.callback_url'));
    }

    public function test_store_list_falls_back_to_address_and_merges_email(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();

        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'store_meta' => ['10' => ['email' => 'store@example.com', 'label' => 'Main']],
        ]);
        $configuration->save();

        Http::fake([
            PaperflyCourierService::SENDER_BASE.'list.php*' => Http::response([
                'records' => [[
                    'id' => '10',
                    'full_name' => 'Chander Alo',
                    'phone_number' => '01711111111',
                    'district_name' => 'Dhaka',
                    'thana_name' => 'Dhanmondi',
                    'address' => 'House 12, Road 5',
                ]],
                'totalRecords' => 150,
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores')
            ->assertOk()
            ->assertJsonPath('data.records.0.address_line', 'House 12, Road 5')
            ->assertJsonPath('data.records.0.email', 'store@example.com')
            ->assertJsonPath('data.records.0.label', 'Main')
            ->assertJsonPath('data.truncated', true);
    }

    public function test_create_sets_the_first_default_and_a_second_create_leaves_it(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $headers = $this->apiHeaders($plainToken, 'https://shop.example.com');

        $lists = [
            [
                $this->paperflyListRow(['id' => '4']),
                $this->paperflyListRow(['id' => '9']),
            ],
            [
                $this->paperflyListRow(['id' => '4']),
                $this->paperflyListRow(['id' => '9']),
                $this->paperflyListRow([
                    'id' => '12',
                    'full_name' => 'Second Store',
                    'phone_number' => '01822222222',
                ]),
            ],
        ];
        $listIndex = 0;

        Http::fake(function ($request) use ($lists, &$listIndex) {
            if (str_contains($request->url(), 'add.php')) {
                return Http::response(['message' => 'created'], 200);
            }

            $records = $lists[$listIndex] ?? $lists[array_key_last($lists)];
            $listIndex++;

            return Http::response([
                'records' => $records,
                'totalRecords' => count($records),
            ], 200);
        });

        $created = $this->withHeaders($headers)->postJson('/api/paperfly/stores/create', $this->paperflyStore());
        $created->assertOk()
            ->assertJsonPath('data.active_store.id', '9')
            ->assertJsonPath('data.store_name', 'Chander Alo')
            ->assertJsonPath('data.records.1.email', 'store@example.com')
            ->assertJsonPath('data.store_meta.9.email', 'store@example.com');

        $second = $this->withHeaders($headers)->postJson('/api/paperfly/stores/create', $this->paperflyStore([
            'full_name' => 'Second Store',
            'phone_number' => '01822222222',
            'email' => 'second@example.com',
            'label' => 'Backup',
        ]));

        $second->assertOk()
            ->assertJsonPath('data.active_store.id', '9')
            ->assertJsonPath('data.store_meta.12.label', 'Backup');

        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $this->assertSame('9', $configuration->settings['active_store']['id']);
    }

    public function test_create_without_a_matching_row_does_not_invent_a_default(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'add.php')) {
                return Http::response(['message' => 'created'], 200);
            }

            return Http::response(['records' => [], 'totalRecords' => 0], 200);
        });

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/create', $this->paperflyStore())
            ->assertOk()
            ->assertJsonPath('data.active_store', null)
            ->assertJsonPath('data.warning', 'Paperfly accepted the store, but it was not in the list yet.');

        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $this->assertArrayNotHasKey('active_store', $configuration->settings ?? []);
    }

    public function test_update_refreshes_the_default_from_the_new_list_row(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'active_store' => [
                'id' => '9',
                'full_name' => 'Chander Alo',
                'phone_number' => '01711111111',
                'email' => 'store@example.com',
                'district_name' => 'Dhaka',
                'thana_name' => 'Dhanmondi',
                'address_line' => 'Old line',
                'label' => 'Main',
            ],
            'store_name' => 'Chander Alo',
        ]);
        $configuration->save();

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'update.php')) {
                return Http::response(['message' => 'updated'], 200);
            }

            return Http::response([
                'records' => [[
                    'id' => '9',
                    'full_name' => 'Chander Alo Gallery',
                    'phone_number' => '01711111111',
                    'district_name' => 'Dhaka',
                    'thana_name' => 'Dhanmondi',
                    'address_line' => 'Listed fresh line',
                ]],
                'totalRecords' => 1,
            ], 200);
        });

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/update', $this->paperflyStore([
                'id' => '9',
                'full_name' => 'Posted name',
                'address' => 'Typed new address here',
                'email' => 'fresh@example.com',
                'label' => 'Counter',
            ]))
            ->assertOk()
            ->assertJsonPath('data.active_store.address_line', 'Listed fresh line')
            ->assertJsonPath('data.active_store.full_name', 'Chander Alo Gallery')
            ->assertJsonPath('data.active_store.email', 'fresh@example.com')
            ->assertJsonPath('data.store_name', 'Chander Alo Gallery');
    }

    public function test_set_default_rejects_an_id_that_is_not_in_the_list(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $active = [
            'id' => '9',
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'email' => 'store@example.com',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address_line' => 'House 12, Road 5',
            'label' => 'Main',
        ];
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'active_store' => $active,
            'store_name' => 'Chander Alo',
        ]);
        $configuration->save();

        Http::fake([
            PaperflyCourierService::SENDER_BASE.'list.php*' => Http::response([
                'records' => [$this->paperflyListRow(['id' => '9'])],
                'totalRecords' => 1,
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/default', [
                'id' => '999',
                'full_name' => 'Posted object',
                'address_line' => 'Should not be saved',
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', false);

        $configuration->refresh();
        $this->assertEquals($active, $configuration->settings['active_store']);
        $this->assertSame('Chander Alo', $configuration->settings['store_name']);
    }

    public function test_timed_out_store_call_leaves_the_default_unchanged(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $active = [
            'id' => '9',
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'email' => '',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address_line' => 'House 12, Road 5',
            'label' => '',
        ];
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'active_store' => $active,
            'store_name' => 'Chander Alo',
        ]);
        $configuration->save();

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/default', ['id' => '9'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Paperfly could not be reached.');

        $configuration->refresh();
        $this->assertEquals($active, $configuration->settings['active_store']);
    }

    public function test_http_200_with_an_error_key_is_a_failure(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $active = [
            'id' => '9',
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'email' => 'store@example.com',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address_line' => 'House 12, Road 5',
            'label' => 'Main',
        ];
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'active_store' => $active,
            'store_name' => 'Chander Alo',
        ]);
        $configuration->save();

        Http::fake([
            PaperflyCourierService::SENDER_BASE.'add.php' => Http::response([
                'error' => 'Store could not be saved.',
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/create', $this->paperflyStore([
                'full_name' => 'Another Shop',
                'phone_number' => '01933333333',
            ]))
            ->assertStatus(400)
            ->assertJsonPath('status', false);

        $configuration->refresh();
        $this->assertEquals($active, $configuration->settings['active_store']);
        $this->assertSame('Chander Alo', $configuration->settings['store_name']);
    }

    public function test_store_form_is_validated_before_the_http_call(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        Http::fake();

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/stores/create', $this->paperflyStore([
                'full_name' => 'Hi',
            ]))
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_create_bulk_order_books_with_the_default_store_and_weight(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'default_weight' => '1.0',
            'store_name' => 'Chander Alo',
            'active_store' => [
                'id' => '9',
                'full_name' => 'Chander Alo',
                'phone_number' => '01711111111',
                'address_line' => 'House 12',
            ],
        ]);
        $configuration->save();

        Http::fake([
            'https://api.paperfly.com.bd/merchant/api/service/new_order_v2.php' => Http::response([
                'success' => [
                    'message' => 'successfully inserted',
                    'tracking_number' => 'Z-051125-63821',
                    'tracking_barcode' => '751820115459',
                ],
                'response_code' => 200,
            ], 200),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'LOC1-44',
                    'recipient_name' => 'Karim Ahmed',
                    'recipient_phone' => '01712345678',
                    'recipient_address' => 'House 12, Road 4, Dhanmondi',
                    'cod_amount' => 850,
                    'item_description' => 'Silk Saree',
                    'item_weight' => 0.4,
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.0.consignment_id', 'Z-051125-63821')
            ->assertJsonPath('data.0.tracking_code', '751820115459')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.error', null);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://api.paperfly.com.bd/merchant/api/service/new_order_v2.php'
                && $request->hasHeader('paperflykey', PaperflyCourierService::PAPERFLY_KEY)
                && ($body['merchantOrderReference'] ?? '') === 'LOC1-44'
                && ($body['storeName'] ?? '') === 'Chander Alo'
                && ($body['productBrief'] ?? '') === 'Silk Saree'
                && ($body['packagePrice'] ?? '') === '850'
                && ($body['max_weight'] ?? '') === '0.4'
                && ($body['customerPhone'] ?? '') === '01712345678';
        });

        $this->assertSame(1, CourierShipment::query()->where('partner', 'paperfly')->count());
    }

    public function test_create_bulk_order_rejects_a_missing_default_store_without_calling_paperfly(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        Http::fake();

        $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'LOC1-44',
                    'recipient_name' => 'Karim Ahmed',
                    'recipient_phone' => '01712345678',
                    'recipient_address' => 'House 12, Road 4',
                    'cod_amount' => 850,
                ]],
            ])
            ->assertStatus(400);

        Http::assertNothingSent();
    }

    public function test_create_bulk_order_keeps_a_bad_phone_off_the_api_and_uses_default_weight(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();
        $this->savePaperfly($plainToken, 'https://shop.example.com')->assertOk();
        $configuration = CourierConfiguration::query()->where('slug', 'paperfly')->first();
        $configuration->settings = array_merge($configuration->settings ?? [], [
            'default_weight' => '1.0',
            'active_store' => [
                'id' => '9',
                'full_name' => 'Chander Alo',
            ],
        ]);
        $configuration->save();

        Http::fake([
            'https://api.paperfly.com.bd/*' => Http::response([
                'success' => [
                    'tracking_number' => 'Z-2',
                    'tracking_barcode' => '99',
                ],
                'response_code' => 200,
            ], 200),
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->postJson('/api/paperfly/create-bulk-order', [
                'orders' => [
                    [
                        'invoice' => 'LOC1-1',
                        'recipient_name' => 'Bad Phone',
                        'recipient_phone' => '123',
                        'recipient_address' => 'House 12, Road 4',
                        'cod_amount' => 10,
                    ],
                    [
                        'invoice' => 'LOC1-2',
                        'recipient_name' => 'Good Phone',
                        'recipient_phone' => '8801712345678',
                        'recipient_address' => 'House 12, Road 4',
                        'cod_amount' => 20,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.0.error', 'Customer phone must be 11 digits starting with 01.')
            ->assertJsonPath('data.1.consignment_id', 'Z-2');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return ($request->data()['max_weight'] ?? '') === '1'
                && ($request->data()['customerPhone'] ?? '') === '01712345678'
                && ($request->data()['merchantOrderReference'] ?? '') === 'LOC1-2';
        });
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function paperflyStore(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'email' => 'store@example.com',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address' => 'House 12, Road 5',
            'label' => 'Main',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function paperflyListRow(array $overrides = []): array
    {
        return array_merge([
            'id' => '10',
            'full_name' => 'Chander Alo',
            'phone_number' => '01711111111',
            'district_name' => 'Dhaka',
            'thana_name' => 'Dhanmondi',
            'address' => 'House 12, Road 5',
        ], $overrides);
    }
}
