<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiKeyRevealTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reveal_license_key_on_demand(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'plain-license-token-value';

        $token = AccessToken::unguarded(function () use ($merchant, $plainToken) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'License',
                'token' => hash('sha256', $plainToken),
                'domain' => 'shop.example.com',
                'status' => true,
                'access_key' => Crypt::encryptString($plainToken),
            ]);
        });

        $response = $this->actingAs($admin)
            ->postJson(route('apiKeys.reveal', $token->id));

        $response->assertOk()
            ->assertJsonPath('token', $plainToken);
    }

    public function test_guest_cannot_reveal_license_key(): void
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $token = AccessToken::unguarded(function () use ($merchant) {
            return AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $merchant->id,
                'name' => 'License',
                'token' => hash('sha256', 'secret'),
                'domain' => 'shop.example.com',
                'status' => true,
                'access_key' => Crypt::encryptString('secret'),
            ]);
        });

        $this->postJson(route('apiKeys.reveal', $token->id))
            ->assertUnauthorized();
    }
}
