<?php

namespace Tests\Feature;

use App\Jobs\Employee\RetryEmployeeStoreSyncJob;
use App\Models\AccessToken;
use App\Models\EmployeeStoreSyncLog;
use App\Models\MerchantEmployee;
use App\Models\Role;
use App\Models\User;
use App\Models\Website;
use App\Services\MerchantEmployeeService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmployeeStoreSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function merchantRoleId(): int
    {
        return (int) Role::query()
            ->where('slug', 'merchant-operator')
            ->where('scope', 'merchant')
            ->value('id');
    }

    /**
     * @return array{0: User, 1: Website, 2: Website, 3: string}
     */
    private function createMerchantWithStores(): array
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant-' . uniqid() . '@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainTokenA = 'license-token-a-' . bin2hex(random_bytes(8));
        $plainTokenB = 'license-token-b-' . bin2hex(random_bytes(8));

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

        AccessToken::unguarded(function () use ($merchant, $siteA, $siteB, $plainTokenA, $plainTokenB) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Shop A Token',
                'token' => hash('sha256', $plainTokenA),
                'access_key' => Crypt::encryptString($plainTokenA),
                'domain' => 'shop-a.example.com',
                'website_id' => $siteA->id,
                'status' => true,
            ]);

            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'Shop B Token',
                'token' => hash('sha256', $plainTokenB),
                'access_key' => Crypt::encryptString($plainTokenB),
                'domain' => 'shop-b.example.com',
                'website_id' => $siteB->id,
                'status' => true,
            ]);
        });

        return [$merchant, $siteA, $siteB, $plainTokenA];
    }

    private function fakeSuccessfulEmployeeStoreHttp(): void
    {
        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/*' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
            'https://shop-b.example.com/wp-json/wooeasylife/v1/employees/*' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
        ]);
    }

    public function test_create_employee_syncs_wordpress_users_on_assigned_stores(): void
    {
        [$merchant, $siteA, $siteB] = $this->createMerchantWithStores();

        $this->fakeSuccessfulEmployeeStoreHttp();

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => true,
        ]);

        $this->assertNotNull($employee->id);

        Http::assertSentCount(4);

        $storeSync = $service->pullLastStoreSync();

        $this->assertCount(2, $storeSync);
        $this->assertTrue(collect($storeSync)->every(fn (array $row) => $row['success'] === true));
    }

    public function test_create_rejects_duplicate_employee_email(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Existing Staff',
            'email' => 'staff@example.com',
            'phone' => '01711110001',
            'status' => true,
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->create($merchant, [
                'name' => 'Duplicate Staff',
                'email' => 'staff@example.com',
                'phone' => '01711110002',
                'role_id' => $this->merchantRoleId(),
                'website_ids' => [$siteA->id],
                'status' => true,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());

            throw $exception;
        }
    }

    public function test_create_rejects_duplicate_employee_phone(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Existing Staff',
            'email' => 'staff-a@example.com',
            'phone' => '01711110001',
            'status' => true,
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->create($merchant, [
                'name' => 'Duplicate Staff',
                'email' => 'staff-b@example.com',
                'phone' => '01711110001',
                'role_id' => $this->merchantRoleId(),
                'website_ids' => [$siteA->id],
                'status' => true,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());

            throw $exception;
        }
    }

    public function test_update_allows_same_employee_email_and_phone(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        $this->fakeSuccessfulEmployeeStoreHttp();

        $employee = MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Existing Staff',
            'email' => 'staff@example.com',
            'phone' => '01711110001',
            'status' => true,
        ]);
        $employee->websites()->sync([$siteA->id]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $updated = $service->update($employee, $merchant, [
            'name' => 'Updated Staff',
            'email' => 'staff@example.com',
            'phone' => '01711110001',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        $this->assertSame('Updated Staff', $updated->name);
    }

    public function test_create_rejects_merchant_account_email(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        $this->fakeSuccessfulEmployeeStoreHttp();

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => $merchant->email,
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);
    }

    public function test_create_rejects_email_blocked_by_wordpress_store_validation(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/validate-wp-user-email' => Http::response([
                'status' => false,
                'message' => 'This email is already used by a store owner or administrator account. Please use a different email for the employee.',
            ], 400),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'owner@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);
    }

    public function test_create_allows_employee_when_wordpress_store_is_unreachable(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/validate-wp-user-email' => function () {
                throw new \RuntimeException('Connection refused');
            },
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/sync-wp-user' => function () {
                throw new \RuntimeException('Connection refused');
            },
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        $this->assertNotNull($employee->id);
    }

    public function test_create_employee_uses_website_base_url_for_wordpress_calls(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        $siteA->update(['domain' => 'localhost', 'base_url' => 'http://localhost:8081/wordpress']);

        Http::fake([
            'http://localhost:8081/wordpress/wp-json/wooeasylife/v1/employees/*' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Local Staff',
            'email' => 'local.staff@example.com',
            'phone' => '01711113333',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        $this->assertNotNull($employee->id);

        Http::assertSent(function ($request) {
            return str_starts_with(
                $request->url(),
                'http://localhost:8081/wordpress/wp-json/wooeasylife/v1/employees/'
            );
        });
    }

    public function test_create_employee_uses_domain_fallback_when_base_url_missing(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/*' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Domain Staff',
            'email' => 'domain.staff@example.com',
            'phone' => '01711114444',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        $this->assertNotNull($employee->id);

        Http::assertSent(function ($request) {
            return str_starts_with(
                $request->url(),
                'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/'
            );
        });

        $storeSync = $service->pullLastStoreSync();

        $this->assertSame(
            'https://shop-a.example.com',
            collect($storeSync)->firstWhere('website_id', $siteA->id)['display_url'] ?? null
        );
    }

    public function test_assignable_websites_include_display_url_metadata(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        $siteA->update([
            'domain' => 'localhost',
            'base_url' => 'http://localhost:8081/wordpress',
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $websites = $service->assignableWebsitesForMerchant($merchant);
        $local = $websites->firstWhere('id', $siteA->id);

        $this->assertNotNull($local);
        $this->assertSame('http://localhost:8081/wordpress', $local['display_url']);
        $this->assertTrue($local['uses_base_url']);
        $this->assertTrue($local['sync_configured']);
    }

    public function test_can_assign_multiple_websites_when_one_store_lacks_plugin_token(): void
    {
        [$merchant, $siteA, $siteB] = $this->createMerchantWithStores();

        AccessToken::query()
            ->where('website_id', $siteB->id)
            ->delete();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/validate-wp-user-email' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/sync-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user created successfully.',
            ], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => true,
        ]);

        $this->assertDatabaseHas('merchant_employee_website', [
            'merchant_employee_id' => $employee->id,
            'website_id' => $siteA->id,
        ]);
        $this->assertDatabaseHas('merchant_employee_website', [
            'merchant_employee_id' => $employee->id,
            'website_id' => $siteB->id,
        ]);

        $storeSync = $service->pullLastStoreSync();

        $this->assertTrue(
            collect($storeSync)->contains(
                fn (array $row) => $row['website_id'] === $siteA->id
                    && $row['action'] === 'sync'
                    && $row['success'] === true
            )
        );

        $this->assertTrue(
            collect($storeSync)->contains(
                fn (array $row) => $row['website_id'] === $siteB->id
                    && $row['action'] === 'sync'
                    && $row['success'] === false
                    && ($row['message'] ?? '') === 'missing_store_target'
            )
        );

        $websites = $service->assignableWebsitesForMerchant($merchant);
        $siteBMeta = $websites->firstWhere('id', $siteB->id);

        $this->assertNotNull($siteBMeta);
        $this->assertFalse($siteBMeta['sync_configured']);
    }

    public function test_update_unassigning_website_deletes_wordpress_user_on_removed_store(): void
    {
        [$merchant, $siteA, $siteB] = $this->createMerchantWithStores();

        Http::fake([
            '*' => Http::response(['status' => true, 'message' => 'ok'], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => true,
        ]);

        Http::fake([
            'https://shop-b.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user deleted successfully.',
            ], 200),
        ]);

        $service->update($employee, $merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://shop-b.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user'
                && $request->hasHeader('X-WEL-Internal-Token');
        });

        $storeSync = $service->pullLastStoreSync();

        $this->assertTrue(
            collect($storeSync)->contains(
                fn (array $row) => $row['website_id'] === $siteB->id
                    && $row['action'] === 'delete'
                    && $row['success'] === true
            )
        );
    }

    public function test_delete_employee_removes_wordpress_users_from_assigned_stores(): void
    {
        [$merchant, $siteA, $siteB] = $this->createMerchantWithStores();

        Http::fake([
            '*' => Http::response(['status' => true, 'message' => 'ok'], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => true,
        ]);

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user deleted successfully.',
            ], 200),
            'https://shop-b.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user deleted successfully.',
            ], 200),
        ]);

        $service->delete($employee, $merchant);

        Http::assertSentCount(2);

        $storeSync = $service->pullLastStoreSync();

        $this->assertCount(2, $storeSync);
        $this->assertTrue(collect($storeSync)->every(fn (array $row) => $row['action'] === 'delete'));
    }

    public function test_create_requires_email(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        try {
            $service->create($merchant, [
                'name' => 'Store Staff',
                'phone' => '01711112222',
                'role_id' => $this->merchantRoleId(),
                'website_ids' => [$siteA->id],
                'status' => true,
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());

            throw $exception;
        }
    }

    public function test_deactivate_employee_deletes_wordpress_users_on_assigned_stores(): void
    {
        [$merchant, $siteA, $siteB] = $this->createMerchantWithStores();

        Http::fake([
            '*' => Http::response(['status' => true, 'message' => 'ok'], 200),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $employee = $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => true,
        ]);

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user deleted successfully.',
            ], 200),
            'https://shop-b.example.com/wp-json/wooeasylife/v1/employees/delete-wp-user' => Http::response([
                'status' => true,
                'message' => 'WordPress user deleted successfully.',
            ], 200),
        ]);

        $service->update($employee, $merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id, $siteB->id],
            'status' => false,
        ]);

        Http::assertSentCount(2);

        $storeSync = $service->pullLastStoreSync();

        $this->assertCount(2, $storeSync);
        $this->assertTrue(collect($storeSync)->every(fn (array $row) => $row['action'] === 'delete'));
    }

    public function test_plugin_create_returns_store_sync_payload(): void
    {
        [$merchant, $siteA, , $plainToken] = $this->createMerchantWithStores();

        $this->fakeSuccessfulEmployeeStoreHttp();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
            'Origin' => 'https://shop-a.example.com',
        ])->postJson('/api/employees', [
            'name' => 'Plugin Staff',
            'email' => 'plugin-staff@example.com',
            'phone' => '01733334444',
            'role_id' => $this->merchantRoleId(),
            'status' => true,
            'website_ids' => [$siteA->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.employee.name', 'Plugin Staff')
            ->assertJsonStructure([
                'data' => [
                    'employee',
                    'store_sync',
                ],
            ]);
    }

    public function test_failed_store_sync_is_logged_and_retry_job_is_scheduled(): void
    {
        Queue::fake();

        [$merchant, $siteA] = $this->createMerchantWithStores();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/validate-wp-user-email' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/sync-wp-user' => Http::response([
                'status' => false,
                'message' => 'forward_failed',
            ], 500),
        ]);

        /** @var MerchantEmployeeService $service */
        $service = app(MerchantEmployeeService::class);

        $service->create($merchant, [
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'role_id' => $this->merchantRoleId(),
            'website_ids' => [$siteA->id],
            'status' => true,
        ]);

        $this->assertDatabaseHas('employee_store_sync_logs', [
            'merchant_user_id' => $merchant->id,
            'website_id' => $siteA->id,
            'action' => 'sync',
            'success' => false,
            'message' => 'forward_failed',
            'retry_scheduled' => true,
        ]);

        Queue::assertPushed(RetryEmployeeStoreSyncJob::class);
    }

    public function test_retry_job_can_resolve_failed_store_sync(): void
    {
        [$merchant, $siteA] = $this->createMerchantWithStores();

        Http::fake([
            'https://shop-a.example.com/wp-json/wooeasylife/v1/employees/sync-wp-user' => Http::response([
                'status' => true,
                'message' => 'ok',
            ], 200),
        ]);

        $employee = \App\Models\MerchantEmployee::create([
            'merchant_user_id' => $merchant->id,
            'role_id' => $this->merchantRoleId(),
            'name' => 'Store Staff',
            'email' => 'staff@example.com',
            'phone' => '01711112222',
            'status' => true,
        ]);
        $employee->websites()->sync([$siteA->id]);

        $log = EmployeeStoreSyncLog::create([
            'merchant_user_id' => $merchant->id,
            'merchant_employee_id' => $employee->id,
            'website_id' => $siteA->id,
            'domain' => 'shop-a.example.com',
            'action' => 'sync',
            'success' => false,
            'message' => 'forward_failed',
            'attempt_count' => 1,
            'max_attempts' => EmployeeStoreSyncLog::MAX_ATTEMPTS,
            'retry_scheduled' => true,
            'last_attempted_at' => now(),
        ]);

        app(\App\Services\Employee\EmployeeStoreSyncRetryService::class)->process($log->id);

        $log->refresh();

        $this->assertTrue($log->success);
        $this->assertNotNull($log->resolved_at);
        $this->assertSame(2, $log->attempt_count);
    }
}
