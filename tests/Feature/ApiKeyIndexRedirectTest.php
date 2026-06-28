<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiKeyIndexRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_api_keys_index_redirects_to_merchants(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('apiKeys.index'))
            ->assertRedirect(route('users.index'));
    }
}
