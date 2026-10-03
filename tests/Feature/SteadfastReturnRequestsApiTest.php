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
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.source', 'packzy')
            ->assertJsonPath('data.authoritative_statuses', []);
    }

    public function test_list_return_requests_accepts_packzy_data_without_status_field(): void
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

        // Real Packzy payload shape: data array, no top-level status, nested consignment.
        Http::fake([
            'portal.packzy.com/api/v1/get_return_requests' => Http::response([
                'data' => [
                    [
                        'id' => 78681,
                        'consignment_id' => 267997491,
                        'reason' => 'Customer er kache dauble parcel gese',
                        'status' => 'pending',
                        'created_at' => '2026-07-07T09:13:37.000000Z',
                        'consignment' => [
                            'consignment_id' => 267997491,
                            'invoice' => 'DAI9-51035',
                            'tracking_code' => 'SFR260706ST17139E8BD',
                            'recipient_name' => 'shoaib',
                            'cod_amount' => 650,
                            'status' => 'delivered',
                        ],
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
            ->assertJsonPath('data.items.0.consignment_id', '267997491')
            ->assertJsonPath('data.items.0.status', 'pending')
            ->assertJsonPath('data.items.0.customer_name', 'shoaib')
            ->assertJsonPath('data.items.0.invoice', 'DAI9-51035')
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

    public function test_list_returns_portal_error_when_login_fails(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        \Illuminate\Support\Facades\Cache::flush();

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
            // Real portal host, login HTML with no CSRF token.
            'www.steadfast.com.bd/*' => Http::response('<form action="/login"></form>', 200),
            'steadfast.com.bd/*' => Http::response('<form action="/login"></form>', 200),
            'scstech.io/*' => Http::response('<form action="/login"></form>', 200),
        ]);

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'status' => 'pending',
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', false);

        $message = (string) $response->json('message');
        $this->assertMatchesRegularExpression(
            '/CSRF token not found|did not create a session cookie/i',
            $message
        );
        $this->assertStringNotContainsString('from-packzy', (string) $response->getContent());
    }

    public function test_list_prefers_portal_cancel_requests_over_packzy_returns(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $pendingHtml = <<<'HTML'
<html><head><meta name="csrf-token" content="csrf-test"></head><body>
<table>
<tr><th>Date</th><th>Id</th><th>Customer Name</th><th>Payment</th><th>Charge</th><th>Action</th><th>Details</th></tr>
<tr>
  <td>19378519 July 23, 2026, 06:55:29 PM<br>Entry @ July 17, 2026, 07:09:52 PM</td>
  <td>272384678</td>
  <td>Sonia</td>
  <td></td>
  <td>820</td>
  <td>Change Status</td>
  <td>View</td>
</tr>
</table>
</body></html>
HTML;

        \Illuminate\Support\Facades\Cache::flush();

        Http::fake(function ($request) use ($pendingHtml) {
            $url = $request->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/get_return_requests')) {
                return Http::response([
                    'data' => [
                        [
                            'id' => 78681,
                            'consignment_id' => 267997491,
                            'status' => 'pending',
                            'reason' => 'packzy-only-row',
                            'customer_name' => 'shoaib',
                        ],
                    ],
                ], 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/')) {
                // Only pending tab (index 0) has rows; other tabs empty.
                if (str_contains($url, '/show/0')) {
                    return Http::response($pendingHtml, 200);
                }

                return Http::response('<html><body><table></table></body></html>', 200);
            }

            return Http::response('not-found', 404);
        });

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'status' => 'pending',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.consignment_id', '272384678')
            ->assertJsonPath('data.items.0.customer_name', 'Sonia')
            ->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonMissing(['consignment_id' => '267997491']);

        $this->assertContains('pending', $response->json('data.authoritative_statuses'));
    }

    public function test_full_pending_page_is_not_authoritative(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $cards = '';
        for ($i = 1; $i <= 20; $i++) {
            $consignment = 300000000 + $i;
            $cards .= '<li class="sf-creq-item" data-sf-creq="'.$i.'">'
                .'<a href="/user/consignment/'.$consignment.'">#'.$consignment.'</a>'
                .'<b>Buyer</b></li>';
        }
        $pendingHtml = '<html><body><ul>'.$cards.'</ul></body></html>';

        \Illuminate\Support\Facades\Cache::flush();

        Http::fake(function ($request) use ($pendingHtml) {
            $url = $request->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/get_return_requests')) {
                return Http::response(['data' => []], 200);
            }
            if (str_contains($url, '/login') && $request->method() === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }
            if (str_contains($url, '/login') && $request->method() === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
            }
            if (str_contains($url, '/user/consignment/cancel-requests/show/0')) {
                return Http::response($pendingHtml, 200);
            }
            if (str_contains($url, '/user/consignment/cancel-requests/show/')) {
                return Http::response('<html><body></body></html>', 200);
            }

            return Http::response('not-found', 404);
        });

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'mode' => 'quick',
            ])
            ->assertOk()
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.counts.pending', 20);

        $this->assertNotContains('pending', $response->json('data.authoritative_statuses') ?? []);
    }

    public function test_dated_portal_list_is_not_authoritative(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $pendingHtml = '<html><body><ul>'
            .'<li class="sf-creq-item" data-sf-creq="9">'
            .'<a href="/user/consignment/299056668">#299056668</a><b>Buyer</b>'
            .'</li></ul></body></html>';

        \Illuminate\Support\Facades\Cache::flush();

        Http::fake(function ($request) use ($pendingHtml) {
            $url = $request->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/get_return_requests')) {
                return Http::response(['data' => []], 200);
            }
            if (str_contains($url, '/login') && $request->method() === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }
            if (str_contains($url, '/login') && $request->method() === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
            }
            if (str_contains($url, '/user/consignment/cancel-requests/show/')) {
                return Http::response($pendingHtml, 200);
            }

            return Http::response('not-found', 404);
        });

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'date' => '2026-09-29',
                'mode' => 'quick',
            ])
            ->assertOk()
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.authoritative_statuses', []);
    }

    public function test_list_scrapes_all_portal_cancel_request_pages(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $page1 = <<<'HTML'
<html><head><meta name="csrf-token" content="csrf-test"></head><body>
<div class="tbody-row"><div class="cell cell_2"><strong>111111111</strong></div>
<div class="cell cell_3">PageOne</div></div>
<cancel-request :item="{&quot;id&quot;:1,&quot;consignment_id&quot;:111111111,&quot;note&quot;:&quot;p1&quot;,&quot;consignment&quot;:{&quot;id&quot;:111111111,&quot;cus_name&quot;:&quot;PageOne&quot;,&quot;cod_amount&quot;:100,&quot;invoice&quot;:&quot;A1&quot;,&quot;track_id&quot;:&quot;T1&quot;}}"></cancel-request>
<ul class="pagination">
  <li class="page-item"><a class="page-link" href="/user/consignment/cancel-requests/show/2?page=1">1</a></li>
  <li class="page-item"><a class="page-link" href="/user/consignment/cancel-requests/show/2?page=2">2</a></li>
</ul>
</body></html>
HTML;

        $page2 = <<<'HTML'
<html><head><meta name="csrf-token" content="csrf-test"></head><body>
<cancel-request :item="{&quot;id&quot;:2,&quot;consignment_id&quot;:222222222,&quot;note&quot;:&quot;p2&quot;,&quot;consignment&quot;:{&quot;id&quot;:222222222,&quot;cus_name&quot;:&quot;PageTwo&quot;,&quot;cod_amount&quot;:200,&quot;invoice&quot;:&quot;A2&quot;,&quot;track_id&quot;:&quot;T2&quot;}}"></cancel-request>
<ul class="pagination">
  <li class="page-item"><a class="page-link" href="/user/consignment/cancel-requests/show/2?page=1">1</a></li>
  <li class="page-item"><a class="page-link" href="/user/consignment/cancel-requests/show/2?page=2">2</a></li>
</ul>
</body></html>
HTML;

        \Illuminate\Support\Facades\Cache::flush();

        Http::fake(function ($request) use ($page1, $page2) {
            $url = $request->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/get_return_requests')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/2')) {
                if (str_contains($url, 'page=2')) {
                    return Http::response($page2, 200);
                }

                return Http::response($page1, 200);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/')) {
                return Http::response('<html><body></body></html>', 200);
            }

            return Http::response('not-found', 404);
        });

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.counts.resend_request', 2);

        $ids = collect($response->json('data.items'))
            ->pluck('consignment_id')
            ->all();

        $this->assertContains('111111111', $ids);
        $this->assertContains('222222222', $ids);
    }

    public function test_update_return_status_posts_confirm_request_with_verify_status(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $detailHtml = <<<'HTML'
<html><head><meta name="csrf-token" content="csrf-test"></head><body>
Cancel Request:
<select class="form-control my-3">
  <option value="1">Confirm Cancel</option>
  <option value="2">Request to Resend</option>
</select>
<cancel-request :item="{&quot;id&quot;:19378519,&quot;consignment_id&quot;:273921193,&quot;note&quot;:&quot;cancel&quot;,&quot;consignment&quot;:{&quot;id&quot;:273921193,&quot;cus_name&quot;:&quot;Asraf&quot;,&quot;cod_amount&quot;:720}}"></cancel-request>
</body></html>
HTML;

        $pendingEmpty = '<html><head><meta name="csrf-token" content="csrf-test"></head><body></body></html>';

        Http::fake(function ($request) use ($detailHtml, $pendingEmpty) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/login') && $method === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }

            if (str_contains($url, '/login') && $method === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/; XSRF-TOKEN=token']);
            }

            if (str_contains($url, '/user/consignment/273921193') && $method === 'GET') {
                return Http::response($detailHtml, 200);
            }

            if (str_contains($url, '/user/consignment/cancel-request/confirm-request') && $method === 'POST') {
                $payload = $request->data();
                if (($payload['item_id'] ?? null) !== '19378519') {
                    return Http::response(['status' => false, 'message' => 'bad item_id'], 422);
                }
                if ((string) ($payload['verify_status'] ?? '') !== '1') {
                    return Http::response(['status' => false, 'message' => 'bad verify_status'], 422);
                }

                return Http::response([
                    'status' => true,
                    'data' => ['verify_status' => 1],
                ], 200);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/0')) {
                return Http::response($pendingEmpty, 200);
            }

            return Http::response('not-found', 404);
        });

        $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests/update-status', [
                'action' => 'confirm_cancel',
                'consignment_id' => '273921193',
                'id' => '19378519',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.consignment_id', '273921193')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.verify_status', 1);
    }

    public function test_list_parses_sf_creq_cards_without_using_parcel_number_as_request_id(): void
    {
        [$user, $token] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastConfig($user);

        $pendingHtml = <<<'HTML'
<html><head><meta name="csrf-token" content="csrf-test"></head><body>
<ul>
<li class="sf-creq-item" data-sf-creq="22315485">
  <div class="sf-creq-item__who">
    <a class="sf-parcels__id" href="https://www.steadfast.com.bd/user/consignment/299879646">#299879646</a>
    <b>Enamul</b>
    <span>Requested 29 Sep 2026, 08:29 PM</span>
    <span>Entered 23 Sep 2026</span>
  </div>
  <p class="sf-creq-item__reason"><span>"Customer asked to cancel"</span></p>
  <span class="sf-creq-item__cod">COD ৳650</span>
</li>
<li class="sf-creq-item" data-sf-creq="22298083">
  <div class="sf-creq-item__who">
    <a class="sf-parcels__id" href="https://www.steadfast.com.bd/user/consignment/298712355">#298712355</a>
    <b>Ariful</b>
    <span>Requested 29 Sep 2026, 06:08 PM</span>
  </div>
  <p class="sf-creq-item__reason is-empty"><span>No reason given.</span></p>
  <span class="sf-creq-item__cod">COD ৳500</span>
</li>
</ul>
<input id="sf-creq-date" value="2026-09-30">
</body></html>
HTML;

        \Illuminate\Support\Facades\Cache::flush();

        Http::fake(function ($request) use ($pendingHtml) {
            $url = $request->url();

            if (str_contains($url, 'portal.packzy.com/api/v1/get_return_requests')) {
                return Http::response(['data' => []], 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'GET') {
                return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
            }

            if (str_contains($url, '/login') && $request->method() === 'POST') {
                return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/0')) {
                return Http::response($pendingHtml, 200);
            }

            if (str_contains($url, '/user/consignment/cancel-requests/show/')) {
                return Http::response('<html><body></body></html>', 200);
            }

            if (str_contains($url, 'latest-cancelled')) {
                return Http::response('date-filter-should-not-run', 500);
            }

            return Http::response('not-found', 404);
        });

        $response = $this->withHeaders($this->apiHeaders($token))
            ->postJson('/api/steadfast/return-requests', [
                'status' => 'pending',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.source', 'portal')
            ->assertJsonPath('data.counts.pending', 2)
            ->assertJsonPath('data.items.0.id', '22315485')
            ->assertJsonPath('data.items.0.consignment_id', '299879646')
            ->assertJsonPath('data.items.0.customer_name', 'Enamul')
            ->assertJsonPath('data.items.0.reason', 'Customer asked to cancel')
            ->assertJsonPath('data.items.0.charge', 650)
            ->assertJsonPath('data.items.0.requested_at', '29 Sep 2026, 08:29 PM')
            ->assertJsonPath('data.items.1.id', '22298083')
            ->assertJsonPath('data.items.1.consignment_id', '298712355')
            ->assertJsonPath('data.items.1.reason', '')
            ->assertJsonPath('data.items.1.charge', 500);

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertNotContains('299879646', $ids);
        $this->assertNotContains('298712355', $ids);
    }
}
