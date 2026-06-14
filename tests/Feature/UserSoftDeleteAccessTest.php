<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSoftDeleteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_trashed_user_cannot_access_get_user_api(): void
    {
        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant@example.com',
            'phone' => '01700000000',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plainToken = 'test-token-12345678901234567890123456789012';

        AccessToken::unguarded(function () use ($user, $plainToken) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Test Token',
                'token' => hash('sha256', $plainToken),
                'domain' => 'https://shop.example.com',
                'status' => true,
            ]);
        });

        $user->update(['status' => false]);
        $user->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://shop.example.com',
        ])->getJson('/api/get-user');

        $response->assertUnauthorized();
    }

    public function test_force_delete_removes_trashed_user_permanently(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $user = User::create([
            'name' => 'Merchant',
            'email' => 'merchant2@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $user->delete();

        $this->actingAs($admin)
            ->delete(route('users.forceDestroy', $user->id))
            ->assertRedirect(route('users.trashed'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(0, User::withTrashed()->where('id', $user->id)->count());
    }
}
