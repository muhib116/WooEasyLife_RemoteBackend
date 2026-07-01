<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\Website;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @group plugin-api
 */
class PluginEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
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

    private function apiHeaders(string $plainToken, string $origin): array
    {
        return [
            'Authorization' => 'Bearer ' . $plainToken,
            'Origin' => $origin,
        ];
    }

    private function merchantRoleId(): int
    {
        return (int) Role::query()
            ->where('slug', 'merchant-operator')
            ->where('scope', 'merchant')
            ->value('id');
    }

    public function test_plugin_lists_all_merchant_employees(): void
    {
        [$merchant, $plainToken] = $this->createMerchantWithToken();

        MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Staff One',
            'phone' => '01711111111',
            'status' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.employees.0.name', 'Staff One')
            ->assertJsonStructure([
                'data' => [
                    'employees' => [
                        ['id', 'name', 'phone', 'email', 'photo_url', 'status', 'role', 'website_ids', 'assigned_to_website'],
                    ],
                    'roles' => [
                        ['id', 'name', 'slug', 'description'],
                    ],
                    'websites',
                    'current_website_id',
                    'website_assignment' => [
                        'website_id',
                        'domain',
                        'total',
                        'employees',
                    ],
                ],
            ]);

        $this->assertArrayNotHasKey('has_portal_access', $response->json('data.employees.0') ?? []);
    }

    public function test_plugin_lists_website_assignment_for_current_domain(): void
    {
        [$merchant, $tokenA] = $this->createMerchantWithToken('shop-a.example.com');
        $tokenB = 'test-token-' . bin2hex(random_bytes(16));

        AccessToken::unguarded(function () use ($merchant, $tokenB) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Shop B Token',
                'token' => hash('sha256', $tokenB),
                'domain' => 'shop-b.example.com',
                'status' => true,
            ]);
        });

        $siteA = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'status' => true,
        ]);

        $siteB = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'status' => true,
        ]);

        $assigned = MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Assigned Staff',
            'phone' => '01711111111',
            'status' => true,
        ]);
        $assigned->websites()->sync([$siteA->id]);

        MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'All Websites Staff',
            'phone' => '01722222222',
            'status' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($tokenA, 'https://shop-a.example.com'))
            ->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonPath('data.current_website_id', $siteA->id)
            ->assertJsonPath('data.website_assignment.website_id', $siteA->id)
            ->assertJsonPath('data.website_assignment.domain', 'shop-a.example.com')
            ->assertJsonPath('data.website_assignment.total', 2)
            ->assertJsonCount(2, 'data.websites');

        $assignedNames = collect($response->json('data.website_assignment.employees'))
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['Assigned Staff', 'All Websites Staff'],
            $assignedNames
        );

        $employees = collect($response->json('data.employees'))->keyBy('name');

        $this->assertTrue($employees->get('Assigned Staff')['assigned_to_website']);
        $this->assertTrue($employees->get('All Websites Staff')['assigned_to_website']);

        $shopBResponse = $this->withHeaders($this->apiHeaders($tokenB, 'https://shop-b.example.com'))
            ->getJson('/api/employees');

        $shopBResponse->assertOk()
            ->assertJsonPath('data.website_assignment.website_id', $siteB->id)
            ->assertJsonPath('data.website_assignment.total', 1)
            ->assertJsonPath('data.website_assignment.employees.0.name', 'All Websites Staff');

        $shopBEmployees = collect($shopBResponse->json('data.employees'))->keyBy('name');

        $this->assertFalse($shopBEmployees->get('Assigned Staff')['assigned_to_website']);
        $this->assertTrue($shopBEmployees->get('All Websites Staff')['assigned_to_website']);
    }

    private function attachAccessKeyToToken(User $merchant, string $plainToken, ?int $websiteId = null): void
    {
        AccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $merchant->id)
            ->where('token', hash('sha256', $plainToken))
            ->update([
                'access_key' => Crypt::encryptString($plainToken),
                'website_id' => $websiteId,
            ]);
    }

    public function test_plugin_create_accepts_json_website_ids_payload(): void
    {
        [$merchant, $plainToken] = $this->createMerchantWithToken('shop-a.example.com');

        $siteA = Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'status' => true,
        ]);

        $this->attachAccessKeyToToken($merchant, $plainToken, $siteA->id);

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/validate-wp-user-email' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/sync-wp-user' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
        ]);

        $headers = $this->apiHeaders($plainToken, 'https://shop-a.example.com');

        $create = $this->withHeaders($headers)->post('/api/employees', [
            'name' => 'Site A Staff',
            'email' => 'site-a-staff@example.com',
            'phone' => '01766666666',
            'role_id' => $this->merchantRoleId(),
            'status' => true,
            'website_ids' => json_encode([$siteA->id]),
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('data.employee.name', 'Site A Staff')
            ->assertJsonPath('data.employee.assigned_to_website', true);

        $employeeId = $create->json('data.employee.id');

        $this->assertDatabaseHas('merchant_employee_website', [
            'merchant_employee_id' => $employeeId,
            'website_id' => $siteA->id,
        ]);
    }

    public function test_plugin_lists_assignable_websites_after_backfill(): void
    {
        [$merchant, $plainToken] = $this->createMerchantWithToken('shop-a.example.com');

        AccessToken::unguarded(function () use ($merchant) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Shop B Token',
                'token' => hash('sha256', 'token-b'),
                'domain' => 'shop-b.example.com',
                'status' => true,
            ]);
        });

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop-a.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        UserPackage::create([
            'title' => 'Standard',
            'domain' => 'shop-b.example.com',
            'user_id' => $merchant->id,
            'package_hub_id' => 1,
            'total_order_can_handle' => 100,
            'remaining_order' => 50,
            'total_order_handled' => 50,
            'per_order_rate' => 1,
            'total_cost' => 100,
            'transaction_charge' => 0,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop-a.example.com'))
            ->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonCount(2, 'data.websites');

        $domains = collect($response->json('data.websites'))->pluck('domain')->all();

        $this->assertEqualsCanonicalizing(
            ['shop-a.example.com', 'shop-b.example.com'],
            $domains
        );

        $firstWebsite = collect($response->json('data.websites'))->firstWhere('domain', 'shop-a.example.com');

        $this->assertSame('https://shop-a.example.com', $firstWebsite['display_url'] ?? null);
        $this->assertFalse($firstWebsite['uses_base_url'] ?? true);
    }

    public function test_plugin_multipart_post_update_matches_plugin_form(): void
    {
        [$merchant, $plainToken] = $this->createMerchantWithToken();
        $headers = $this->apiHeaders($plainToken, 'https://shop.example.com');

        $create = $this->withHeaders($headers)->post('/api/employees', [
            'name' => 'Before Edit',
            'phone' => '01777777777',
            'email' => 'before-edit@example.com',
            'role_id' => $this->merchantRoleId(),
            'status' => true,
            'website_ids' => '[]',
        ], ['Accept' => 'application/json']);

        $create->assertCreated();
        $employeeId = $create->json('data.employee.id');

        $update = $this->withHeaders($headers)->post('/api/employees/' . $employeeId, [
            'name' => 'After Edit',
            'phone' => '01777777777',
            'email' => 'edited@example.com',
            'address' => 'Updated address',
            'role_id' => $this->merchantRoleId(),
            'status' => '0',
            'notes' => 'Updated from plugin form',
            'website_ids' => '[]',
        ], ['Accept' => 'application/json']);

        $update->assertOk()
            ->assertJsonPath('data.employee.name', 'After Edit')
            ->assertJsonPath('data.employee.email', 'edited@example.com')
            ->assertJsonPath('data.employee.status', false);

        $this->assertDatabaseHas('merchant_employees', [
            'id' => $employeeId,
            'name' => 'After Edit',
            'email' => 'edited@example.com',
            'status' => false,
        ]);
    }

    public function test_plugin_creates_updates_and_deletes_employee(): void
    {
        [$merchant, $plainToken] = $this->createMerchantWithToken();
        $headers = $this->apiHeaders($plainToken, 'https://shop.example.com');

        $create = $this->withHeaders($headers)->postJson('/api/employees', [
            'name' => 'API Staff',
            'phone' => '01722222222',
            'email' => 'api-staff@example.com',
            'role_id' => $this->merchantRoleId(),
            'status' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.employee.name', 'API Staff');

        $employeeId = $create->json('data.employee.id');

        $this->withHeaders($headers)
            ->getJson('/api/employees/' . $employeeId)
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'API Staff');

        $this->withHeaders($headers)
            ->putJson('/api/employees/' . $employeeId, [
                'name' => 'Updated API Staff',
                'phone' => '01722222222',
                'role_id' => $this->merchantRoleId(),
            ])
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Updated API Staff');

        $this->withHeaders($headers)
            ->deleteJson('/api/employees/' . $employeeId)
            ->assertOk();

        $this->assertDatabaseMissing('merchant_employees', [
            'id' => $employeeId,
            'merchant_user_id' => $merchant->id,
        ]);
    }

    public function test_second_website_token_manages_same_merchant_employees(): void
    {
        [$merchant, $tokenA] = $this->createMerchantWithToken('shop-a.example.com');
        $tokenB = 'test-token-' . bin2hex(random_bytes(16));

        AccessToken::unguarded(function () use ($merchant, $tokenB) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Shop B Token',
                'token' => hash('sha256', $tokenB),
                'domain' => 'shop-b.example.com',
                'status' => true,
            ]);
        });

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-a.example.com',
            'status' => true,
        ]);

        Website::create([
            'user_id' => $merchant->id,
            'domain' => 'shop-b.example.com',
            'status' => true,
        ]);

        $created = $this->withHeaders($this->apiHeaders($tokenA, 'https://shop-a.example.com'))
            ->postJson('/api/employees', [
                'name' => 'Shared Staff',
                'email' => 'shared-staff@example.com',
                'phone' => '01733333333',
                'role_id' => $this->merchantRoleId(),
            ]);

        $employeeId = $created->json('data.employee.id');

        $this->withHeaders($this->apiHeaders($tokenB, 'https://shop-b.example.com'))
            ->getJson('/api/employees')
            ->assertOk()
            ->assertJsonPath('data.employees.0.id', $employeeId);

        $this->withHeaders($this->apiHeaders($tokenB, 'https://shop-b.example.com'))
            ->post('/api/employees/' . $employeeId, [
                'name' => 'Updated From Shop B',
                'email' => 'shared-staff@example.com',
                'phone' => '01733333333',
                'role_id' => $this->merchantRoleId(),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.employee.name', 'Updated From Shop B');
    }

    public function test_plugin_cannot_access_other_merchant_employee(): void
    {
        [$merchantA, $tokenA] = $this->createMerchantWithToken('shop-a.example.com');
        [$merchantB] = $this->createMerchantWithToken('shop-b.example.com');

        $employee = MerchantEmployee::create([
            'merchant_user_id' => $merchantB->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Other Merchant Staff',
            'phone' => '01744444444',
            'status' => true,
        ]);

        $this->withHeaders($this->apiHeaders($tokenA, 'https://shop-a.example.com'))
            ->getJson('/api/employees/' . $employee->id)
            ->assertNotFound();

        $this->withHeaders($this->apiHeaders($tokenA, 'https://shop-a.example.com'))
            ->putJson('/api/employees/' . $employee->id, [
                'name' => 'Hijack',
                'phone' => '01744444444',
                'role_id' => $this->merchantRoleId(),
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('merchant_employees', [
            'id' => $employee->id,
            'name' => 'Other Merchant Staff',
        ]);
    }

    public function test_plugin_employee_photo_upload(): void
    {
        Storage::fake('public');

        [, $plainToken] = $this->createMerchantWithToken();

        $response = $this->withHeaders($this->apiHeaders($plainToken, 'https://shop.example.com'))
            ->post('/api/employees', [
                'name' => 'Photo Staff',
                'phone' => '01755555555',
                'email' => 'photo-staff@example.com',
                'role_id' => $this->merchantRoleId(),
                'photo' => UploadedFile::fake()->image('employee.jpg'),
            ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $employee = MerchantEmployee::query()->where('name', 'Photo Staff')->firstOrFail();

        $this->assertNotNull($employee->photo);
        Storage::disk('public')->assertExists($employee->photo);
    }

    public function test_plugin_employees_require_valid_token_and_origin(): void
    {
        [, $plainToken] = $this->createMerchantWithToken();

        $this->getJson('/api/employees')->assertUnauthorized();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/employees')->assertStatus(400);
    }
}
