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
     */
    private function postWithCookies(string $uri, array $data, array $cookies = [])
    {
        $this->preparePanelRuntime($cookies);

        return $this->post($uri, $data);
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
