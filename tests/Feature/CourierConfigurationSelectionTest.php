<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierConfigurationSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function createMerchantWithToken(string $name, string $domain): array
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.com',
            'phone' => '017'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'test-token-'.bin2hex(random_bytes(16));

        AccessToken::unguarded(function () use ($user, $plainToken, $domain) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'License',
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

    public function test_booking_uses_the_same_steadfast_row_shown_in_settings_when_duplicates_exist(): void
    {
        [$bizmela] = $this->createMerchantWithToken('Local Bizmela', 'local-bizmela.test');
        [$storevion, $storevionToken] = $this->createMerchantWithToken('Local Storevion', 'local-storevion.test');

        CourierConfiguration::create([
            'user_id' => $bizmela->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'bizmela-api-key',
            'secret_key' => 'bizmela-secret',
            'is_active' => true,
        ]);

        CourierConfiguration::create([
            'user_id' => $storevion->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'bizmela-api-key',
            'secret_key' => 'bizmela-secret',
            'is_active' => true,
        ]);

        $latest = CourierConfiguration::create([
            'user_id' => $storevion->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'storevion-api-key',
            'secret_key' => 'storevion-secret',
            'is_active' => true,
        ]);

        $headers = $this->apiHeaders($storevionToken, 'https://local-storevion.test');

        $this->withHeaders($headers)
            ->postJson('/api/courier/get-configuration')
            ->assertOk()
            ->assertJsonPath('data.steadfast.id', $latest->id)
            ->assertJsonPath('data.steadfast.api_key', 'storevion-api-key');

        Http::fake([
            'https://portal.packzy.com/api/v1/create_order/bulk-order' => Http::response([
                'status' => 200,
                'data' => [[
                    'consignment_id' => 'C-1',
                    'invoice' => 'INV1',
                    'status' => 'in_review',
                ]],
            ], 200),
        ]);

        $this->withHeaders($headers)
            ->postJson('/api/steadfast/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'INV1',
                    'recipient_name' => 'Karim',
                    'recipient_phone' => '01711111111',
                    'recipient_address' => 'Dhaka Bangladesh address line',
                    'cod_amount' => 100,
                ]],
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order')
                && $request->hasHeader('Api-Key', 'storevion-api-key')
                && $request->hasHeader('Secret-Key', 'storevion-secret');
        });
    }

    public function test_save_without_id_updates_the_existing_steadfast_row(): void
    {
        [$user, $token] = $this->createMerchantWithToken('Local Storevion', 'local-storevion.test');

        $existing = CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'old-api-key',
            'secret_key' => 'old-secret',
            'is_active' => true,
        ]);

        $this->withHeaders($this->apiHeaders($token, 'https://local-storevion.test'))
            ->postJson('/api/courier/save-configuration', [
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'new-api-key',
                'secret_key' => 'new-secret',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.api_key', 'new-api-key');

        $this->assertSame(1, CourierConfiguration::query()->where('user_id', $user->id)->where('slug', 'steadfast')->count());
        $existing->refresh();
        $this->assertSame('new-api-key', $existing->api_key);
    }

    public function test_save_cannot_overwrite_another_merchants_configuration(): void
    {
        [$bizmela] = $this->createMerchantWithToken('Local Bizmela', 'local-bizmela.test');
        [$storevion, $token] = $this->createMerchantWithToken('Local Storevion', 'local-storevion.test');

        $bizmelaConfig = CourierConfiguration::create([
            'user_id' => $bizmela->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'bizmela-api-key',
            'secret_key' => 'bizmela-secret',
            'is_active' => true,
        ]);

        $this->withHeaders($this->apiHeaders($token, 'https://local-storevion.test'))
            ->postJson('/api/courier/save-configuration', [
                'id' => $bizmelaConfig->id,
                'title' => 'Steadfast',
                'slug' => 'steadfast',
                'api_key' => 'stolen-api-key',
                'secret_key' => 'stolen-secret',
                'is_active' => true,
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', false);

        $bizmelaConfig->refresh();
        $this->assertSame($bizmela->id, (int) $bizmelaConfig->user_id);
        $this->assertSame('bizmela-api-key', $bizmelaConfig->api_key);
        $this->assertSame(0, CourierConfiguration::query()->where('user_id', $storevion->id)->count());
    }

    public function test_a_merchant_with_one_steadfast_row_still_books_with_that_row(): void
    {
        [$user, $token] = $this->createMerchantWithToken('Single Store', 'single-store.test');

        CourierConfiguration::create([
            'user_id' => $user->id,
            'title' => 'Steadfast',
            'slug' => 'steadfast',
            'api_key' => 'only-api-key',
            'secret_key' => 'only-secret',
            'is_active' => true,
        ]);

        Http::fake([
            'https://portal.packzy.com/api/v1/create_order/bulk-order' => Http::response([
                'status' => 200,
                'data' => [[
                    'consignment_id' => 'C-2',
                    'invoice' => 'INV2',
                    'status' => 'in_review',
                ]],
            ], 200),
        ]);

        $this->withHeaders($this->apiHeaders($token, 'https://single-store.test'))
            ->postJson('/api/steadfast/create-bulk-order', [
                'orders' => [[
                    'invoice' => 'INV2',
                    'recipient_name' => 'Karim',
                    'recipient_phone' => '01711111111',
                    'recipient_address' => 'Dhaka Bangladesh address line',
                    'cod_amount' => 50,
                ]],
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Api-Key', 'only-api-key')
                && $request->hasHeader('Secret-Key', 'only-secret');
        });
    }
}
