<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MerchantSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.google.client_id', 'google-client-id');
        Config::set('services.google.client_secret', 'google-client-secret');
        Config::set('services.facebook.client_id', 'facebook-client-id');
        Config::set('services.facebook.client_secret', 'facebook-client-secret');
    }

    public function test_merchant_login_page_exposes_enabled_social_providers(): void
    {
        $response = $this->get('/marchent/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/MerchantLogin')
            ->where('socialProviders', ['google', 'facebook']));
    }

    public function test_google_oauth_creates_merchant_and_redirects_to_portal(): void
    {
        Socialite::fake('google', $this->fakeSocialiteUser(
            id: 'google-oauth-1',
            name: 'OAuth Merchant',
            email: 'oauth-merchant@example.com',
        ));

        $response = $this->get('/marchent/auth/google/callback');

        $response->assertRedirect('/portal');
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'oauth-merchant@example.com',
            'google_id' => 'google-oauth-1',
            'role' => 'user',
            'status' => true,
        ]);
    }

    public function test_google_oauth_links_existing_merchant_by_email(): void
    {
        User::create([
            'name' => 'Existing Merchant',
            'email' => 'existing@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        Socialite::fake('google', $this->fakeSocialiteUser(
            id: 'google-oauth-2',
            name: 'Existing Merchant',
            email: 'existing@example.com',
        ));

        $this->get('/marchent/auth/google/callback')->assertRedirect('/portal');

        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'google_id' => 'google-oauth-2',
            'role' => 'user',
        ]);
    }

    public function test_google_oauth_rejects_admin_accounts(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        Socialite::fake('google', $this->fakeSocialiteUser(
            id: 'google-oauth-3',
            name: 'Admin',
            email: 'admin@example.com',
        ));

        $response = $this->get('/marchent/auth/google/callback');

        $response->assertRedirect(route('merchant.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_facebook_oauth_redirect_route_is_available(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://facebook.com/oauth'));

        Socialite::shouldReceive('driver')->once()->with('facebook')->andReturn($provider);

        $response = $this->get('/marchent/auth/facebook/redirect');

        $response->assertRedirect('https://facebook.com/oauth');
    }

    private function fakeSocialiteUser(string $id, string $name, string $email): SocialiteUserContract
    {
        return (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }
}
