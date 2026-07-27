<?php

namespace Tests\Unit;

use App\Models\AccessToken;
use App\Models\User;
use App\Services\ApiAccessTokenResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiAccessTokenResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_memoizes_token_on_request(): void
    {
        $user = User::create([
            'name' => 'Resolver Merchant',
            'email' => 'resolver-'.uniqid().'@example.com',
            'phone' => '01700000001',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plain = 'resolver-token-'.bin2hex(random_bytes(8));
        AccessToken::unguarded(function () use ($user, $plain) {
            AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Resolver',
                'token' => hash('sha256', $plain),
                'domain' => 'shop.example.com',
                'status' => true,
            ]);
        });

        $request = Request::create('/api/get-user', 'GET');
        $resolver = app(ApiAccessTokenResolver::class);

        $first = $resolver->resolve($plain, $request);
        $second = $resolver->resolve($plain, $request);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($first, $second);
    }

    public function test_touch_last_used_throttles_writes(): void
    {
        $user = User::create([
            'name' => 'Touch Merchant',
            'email' => 'touch-'.uniqid().'@example.com',
            'phone' => '01700000002',
            'password' => Hash::make('password'),
            'role' => 'user',
            'status' => true,
        ]);

        $plain = 'touch-token-'.bin2hex(random_bytes(8));
        $token = null;
        AccessToken::unguarded(function () use ($user, $plain, &$token) {
            $token = AccessToken::create([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'Touch',
                'token' => hash('sha256', $plain),
                'domain' => 'shop.example.com',
                'status' => true,
                'last_used_at' => now(),
            ]);
        });

        $resolver = app(ApiAccessTokenResolver::class);
        $before = $token->fresh()->last_used_at?->timestamp;

        $resolver->touchLastUsed($token->fresh(), 300);
        $after = $token->fresh()->last_used_at?->timestamp;

        $this->assertSame($before, $after);
    }
}
