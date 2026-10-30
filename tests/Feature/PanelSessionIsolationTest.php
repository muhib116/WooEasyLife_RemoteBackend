<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelSession;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelSessionIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $sessionPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->sessionPath = storage_path('framework/testing-panel-sessions');
        File::deleteDirectory($this->sessionPath);
        File::ensureDirectoryExists($this->sessionPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sessionPath);

        parent::tearDown();
    }

    public function test_admin_and_merchant_stay_signed_in_side_by_side(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-side@example.com',
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant Side',
            'email' => 'merchant-side@example.com',
            'phone' => '01700000881',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $adminCookies = $this->loginAdmin($admin->email);
        $merchantCookies = $this->loginMerchant($merchant->email);
        $both = array_merge($adminCookies, $merchantCookies);

        $this->assertArrayHasKey(PanelSession::merchantCookieName(), $merchantCookies);
        $this->assertArrayHasKey(PanelSession::baseCookieName(), $adminCookies);
        $this->assertArrayHasKey(PanelSession::MERCHANT_XSRF_COOKIE, $merchantCookies);
        $this->assertArrayHasKey(PanelSession::ADMIN_XSRF_COOKIE, $adminCookies);

        $this->getWithCookies('/portal/profile', $both)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.email', 'merchant-side@example.com'));

        $this->getWithCookies('/profile', $both)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.email', 'admin-side@example.com'));

        $this->getWithCookies('/marchent/login', $this->onlyCookies($adminCookies, false))
            ->assertOk();

        $this->getWithCookies('/muhib/login', $this->onlyCookies($merchantCookies, true))
            ->assertOk();

        $afterLogout = $this->postWithCookies('/marchent/logout', $both);
        $afterLogout->assertRedirect(route('merchant.login'));

        $this->getWithCookies(
            '/portal',
            $this->withoutRemember($this->onlyCookies($this->cookiesFrom($afterLogout), true))
        )->assertRedirect(route('merchant.login'));

        $this->getWithCookies('/profile', $this->onlyCookies($adminCookies, false))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.email', 'admin-side@example.com'));
    }

    public function test_live_csrf_tokens_stay_on_their_own_panel(): void
    {
        $this->app->instance('env', 'local');

        $merchant = User::create([
            'name' => 'CSRF Merchant',
            'email' => 'csrf-merchant@example.com',
            'phone' => '01700000771',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $merchantPage = $this->getWithCookies('/marchent/login', []);
        $merchantPage->assertOk();
        $merchantCookies = $this->onlyCookies($this->cookiesFrom($merchantPage), true);
        $merchantToken = $merchantCookies[PanelSession::MERCHANT_XSRF_COOKIE] ?? null;
        $this->assertNotEmpty($merchantToken);

        $this->postWithCookies('/marchent/login', [
            'email' => '01700000771',
            'password' => 'password',
        ], $merchantCookies)->assertStatus(419);

        $this->postWithCookies('/marchent/login', [
            'email' => '01700000771',
            'password' => 'password',
        ], $merchantCookies, [
            'X-XSRF-TOKEN' => $merchantToken,
        ])->assertRedirect('/portal');

        $admin = User::factory()->create([
            'email' => 'csrf-admin@example.com',
            'role' => 'admin',
            'status' => true,
        ]);

        $adminPage = $this->getWithCookies('/muhib/login', []);
        $adminPage->assertOk();
        $adminCookies = $this->onlyCookies($this->cookiesFrom($adminPage), false);
        $adminToken = $adminCookies[PanelSession::ADMIN_XSRF_COOKIE] ?? null;
        $this->assertNotEmpty($adminToken);

        $this->postWithCookies('/muhib/login/unlock', [], $adminCookies, [
            'X-XSRF-TOKEN' => $merchantToken,
        ])->assertStatus(419);

        $unlocked = $this->postWithCookies('/muhib/login/unlock', [], $adminCookies, [
            'X-XSRF-TOKEN' => $adminToken,
        ]);
        $unlocked->assertRedirect(route('login'));

        $adminCookies = $this->onlyCookies($this->cookiesFrom($unlocked), false);
        $adminToken = $adminCookies[PanelSession::ADMIN_XSRF_COOKIE] ?? $adminToken;

        $this->postWithCookies('/muhib/login', [
            'email' => $admin->email,
            'password' => 'password',
        ], $adminCookies, [
            'X-XSRF-TOKEN' => $merchantToken,
        ])->assertStatus(419);

        $this->postWithCookies('/muhib/login', [
            'email' => $admin->email,
            'password' => 'password',
        ], $adminCookies, [
            'X-XSRF-TOKEN' => $adminToken,
        ])->assertRedirect('/dashboard');
    }

    public function test_old_merchant_session_does_not_block_the_admin_login_page(): void
    {
        $merchant = User::create([
            'name' => 'Old Session Merchant',
            'email' => 'old-session-merchant@example.com',
            'phone' => '01700000772',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $this->actingAs($merchant)
            ->get('/muhib/login')
            ->assertOk();

        $this->assertGuest();

        $admin = User::factory()->create([
            'email' => 'still-admin@example.com',
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get('/muhib/login')
            ->assertRedirect('/dashboard');
    }

    /**
     * @return array<string, string>
     */
    private function loginAdmin(string $email): array
    {
        $unlocked = $this->postWithCookies('/muhib/login/unlock', []);
        $unlocked->assertRedirect(route('login'));

        $loggedIn = $this->postWithCookies('/muhib/login', [
            'email' => $email,
            'password' => 'password',
            'remember' => '1',
        ], $this->cookiesFrom($unlocked));

        $loggedIn->assertRedirect('/dashboard');

        return $this->cookiesFrom($loggedIn);
    }

    /**
     * @return array<string, string>
     */
    private function loginMerchant(string $email): array
    {
        $loggedIn = $this->postWithCookies('/marchent/login', [
            'email' => $email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $loggedIn->assertRedirect('/portal');

        return $this->cookiesFrom($loggedIn);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function getWithCookies(string $uri, array $cookies)
    {
        $this->preparePanelRuntime($cookies);

        return $this->get($uri);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $cookies
     * @param  array<string, string>  $headers
     */
    private function postWithCookies(string $uri, array $data, array $cookies = [], array $headers = [])
    {
        $this->preparePanelRuntime($cookies);

        return $this->post($uri, $data, $headers);
    }

    /**
     * @param  array<string, string>  $cookies
     */
    private function preparePanelRuntime(array $cookies): void
    {
        config([
            'session.driver' => 'file',
            'session.files' => $this->sessionPath,
        ]);

        if ($this->app->resolved('session')) {
            $this->app->make('session')->forgetDrivers();
        }

        $this->app->forgetInstance('session.store');

        if ($this->app->resolved('auth')) {
            $this->app->make('auth')->forgetGuards();
        }

        $this->defaultCookies = [];
        $this->unencryptedCookies = $cookies;
    }

    /**
     * @param  array<string, string>  $cookies
     * @return array<string, string>
     */
    private function withoutRemember(array $cookies): array
    {
        return array_filter(
            $cookies,
            fn (string $name): bool => ! str_starts_with($name, 'remember_'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Queued cookies from the other panel can stick to later responses inside
     * one test process. Keep only the cookies that belong to the panel.
     *
     * @param  array<string, string>  $cookies
     * @return array<string, string>
     */
    private function onlyCookies(array $cookies, bool $merchant): array
    {
        return array_filter(
            $cookies,
            function (string $name) use ($merchant): bool {
                $isMerchantCookie = str_contains($name, 'panel_merchant')
                    || str_contains($name, 'MERCHANT')
                    || str_starts_with($name, 'remember_merchant_');

                return $merchant ? $isMerchantCookie : ! $isMerchantCookie;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return array<string, string>
     */
    private function cookiesFrom($response): array
    {
        $cookies = [];

        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        return $cookies;
    }
}
