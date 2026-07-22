<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\CourierConfiguration;
use App\Models\PackageHub;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Courier\SteadfastParcelNotesService;
use App\Support\PackageCatalogFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SteadfastParcelNotesApiTest extends TestCase
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
            'title' => 'Parcel Notes Plan',
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

    public function test_parcel_notes_requires_feature_when_catalog_plan_disables_it(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => false,
            'courier_automation' => false,
            'fraud_customer_checker' => true,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Parcel notes or courier automation is not included in your current plan.');
    }

    public function test_parcel_notes_denied_without_active_package(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachSteadfastPortalCredentials($user);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertStatus(403);
    }

    public function test_parcel_notes_allowed_when_inferred_from_courier_automation(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $this->mock(SteadfastParcelNotesService::class, function ($mock) {
            $mock->shouldReceive('fetchNotes')
                ->once()
                ->andReturn([
                    'consignment_id' => '12345678',
                    'merchant_note' => 'ok',
                    'cus_address' => 'Dhaka',
                    'cod_amount' => 100,
                    'notes' => [],
                    'rider' => null,
                ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.consignment_id', '12345678')
            ->assertJsonPath('data.merchant_note', 'ok');
    }

    public function test_parcel_notes_allowed_with_courier_automation_even_when_notes_feature_false(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'courier_automation' => true,
            'parcel_note_history' => false,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $this->mock(SteadfastParcelNotesService::class, function ($mock) {
            $mock->shouldReceive('fetchNotes')
                ->once()
                ->andReturn([
                    'consignment_id' => '12345678',
                    'merchant_note' => 'from-return-flow',
                    'cus_address' => 'Dhaka',
                    'cod_amount' => 100,
                    'notes' => [],
                    'rider' => null,
                ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.merchant_note', 'from-return-flow');
    }

    public function test_parcel_notes_requires_portal_credentials(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Steadfast portal username/password are not configured.');
    }

    public function test_parcel_notes_validates_consignment_id(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => true,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => 'abc',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_parcel_note_happy_path(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => true,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $this->mock(SteadfastParcelNotesService::class, function ($mock) {
            $mock->shouldReceive('updateMerchantNote')
                ->once()
                ->withArgs(function (string $consignmentId, string $note, array $credentials, array $overrides) {
                    return $consignmentId === '12345678'
                        && $note === 'call later'
                        && ($credentials['username'] ?? null) === 'merchant@steadfast.test'
                        && ($overrides['cus_address'] ?? null) === 'Gulshan'
                        && (float) ($overrides['cod_amount'] ?? 0) === 250.0;
                })
                ->andReturn([
                    'consignment_id' => '12345678',
                    'note' => 'call later',
                    'cus_address' => 'Gulshan',
                    'cod_amount' => 250,
                ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes/update', [
                'consignment_id' => '12345678',
                'note' => 'call later',
                'cus_address' => 'Gulshan',
                'cod_amount' => 250,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Parcel updated')
            ->assertJsonPath('data.note', 'call later');
    }

    public function test_update_parcel_note_requires_at_least_one_field(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => true,
        ]);
        $this->attachSteadfastPortalCredentials($user);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes/update', [
                'consignment_id' => '12345678',
                'note' => '   ',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Provide a note, address, COD amount, or customer details to update.');
    }

    public function test_legacy_plan_can_access_parcel_notes(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();

        UserPackage::create([
            'title' => 'Legacy',
            'domain' => 'shop.example.com',
            'user_id' => $user->id,
            'package_hub_id' => 1,
            'plan_type' => 'legacy',
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $this->attachSteadfastPortalCredentials($user);

        $this->mock(SteadfastParcelNotesService::class, function ($mock) {
            $mock->shouldReceive('fetchNotes')->once()->andReturn([
                'consignment_id' => '12345678',
                'merchant_note' => null,
                'cus_address' => null,
                'cod_amount' => null,
                'notes' => [],
                'rider' => null,
            ]);
        });

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->postJson('/api/steadfast/parcel-notes', [
                'consignment_id' => '12345678',
            ]);

        $response->assertOk()->assertJsonPath('status', true);
    }

    public function test_get_user_includes_parcel_note_history_feature_key(): void
    {
        [$user, $plainToken] = $this->createMerchantWithToken();
        $this->attachCatalogPackage($user, [
            'parcel_note_history' => true,
            'fraud_customer_checker' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken))
            ->getJson('/api/get-user');

        $response->assertOk()
            ->assertJsonPath('active_package.features.parcel_note_history', true);

        $this->assertArrayHasKey(
            'parcel_note_history',
            $response->json('active_package.features')
        );
        $this->assertCount(
            count(PackageCatalogFeatures::powerKeys()),
            $response->json('active_package.features')
        );
    }
}
