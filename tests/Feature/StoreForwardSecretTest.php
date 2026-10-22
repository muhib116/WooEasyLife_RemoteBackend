<?php

use App\Models\AccessToken;
use App\Models\CourierAccount;
use App\Models\CourierForwardRetry;
use App\Models\CourierShipment;
use App\Models\User;
use App\Services\Courier\CourierWebhookSyncService;
use App\Services\Courier\WordPressCourierForwarder;
use App\Services\Store\StoreForwardSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function forwardSecretUser(): User
{
    return User::factory()->create();
}

function forwardSecretToken(User $user, string $license): AccessToken
{
    $token = new AccessToken();
    $token->forceFill([
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'license',
        'token' => hash('sha256', 'bearer-'.$user->id),
        'abilities' => ['*'],
        'access_key' => encrypt($license),
        'domain' => 'lovenest.site',
        'status' => true,
    ]);
    $token->save();

    return $token;
}

/**
 * The plugin API group runs check.token + check.tokenDomain, so a store call
 * must carry its license bearer and an Origin that matches the token domain.
 *
 * @return array<string, string>
 */
function forwardSecretHeaders(User $user): array
{
    return [
        'Authorization' => 'Bearer bearer-'.$user->id,
        'Origin' => 'https://lovenest.site',
    ];
}

it('signs courier forwards with the store secret', function () {
    $license = 'stored-license-key';
    $secret = str_repeat('a', 64);
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, $license);

    expect(app(StoreForwardSigner::class)->remember($token, $secret))->toBeTrue();
    expect(app(StoreForwardSigner::class)->remember($token->fresh(), $secret))->toBeFalse();
    expect(app(StoreForwardSigner::class)->signingKey($token->fresh()))->toBe($secret);

    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-'.$user->id),
        'is_active' => true,
    ]);

    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '311020588',
        'invoice' => 'INV-1988',
        'wc_order_id' => 1988,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);

    Http::fake([
        'https://lovenest.site/*' => Http::response(['status' => 'success'], 200),
    ]);

    $payload = [
        'partner' => 'steadfast',
        'order_id' => 1988,
        'raw_status' => 'in_review',
        'updated_at' => '2026-10-22 11:51:02',
    ];

    $result = app(WordPressCourierForwarder::class)->forward($shipment, $payload);

    expect($result['success'])->toBeTrue();

    Http::assertSent(function ($request) use ($secret, $license) {
        $body = (string) $request->body();
        $signature = (string) $request->header('X-WEL-Internal-Token')[0];

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature)
            && ! hash_equals(hash_hmac('sha256', $body, $license), $signature);
    });
});

it('reports a store that refuses the signature as auth_rejected', function () {
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, 'stored-license-key');
    app(StoreForwardSigner::class)->remember($token, str_repeat('c', 64));

    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-401-'.$user->id),
        'is_active' => true,
    ]);
    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '311221304',
        'wc_order_id' => 2101,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);

    Http::fake([
        'https://lovenest.site/*' => Http::response(['message' => 'Unauthorized.'], 401),
    ]);

    $result = app(WordPressCourierForwarder::class)->forward($shipment->fresh(), [
        'partner' => 'steadfast',
        'order_id' => 2101,
        'raw_status' => 'delivered',
    ]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('auth_rejected');
});

it('never posts to the live apply route while probing reachability', function () {
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, 'stored-license-key');
    app(StoreForwardSigner::class)->remember($token, str_repeat('d', 64));

    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-noop-'.$user->id),
        'is_active' => true,
    ]);
    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '311107948',
        'wc_order_id' => 2102,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);

    Http::fake([
        'https://lovenest.site/*' => Http::response(['status' => 'success'], 200),
    ]);

    $probe = app(WordPressCourierForwarder::class)->probeReachability($shipment, $token->fresh());

    expect($probe['forward_probe_status'])->toBe('reachable');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'courier-status/apply'));
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), 'wooeasylife/v1/license-status'));
});

it('stores the secret through the secret-only route and revives failed forwards', function () {
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, 'stored-license-key');
    $secret = str_repeat('e', 64);
    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'sandbox',
        'credential_hash' => hash('sha256', 'cred-route-'.$user->id),
        'is_active' => true,
    ]);
    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'sandbox',
        'consignment_id' => '311020999',
        'wc_order_id' => 2103,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);
    $exhausted = CourierForwardRetry::query()->create([
        'shipment_id' => $shipment->id,
        'payload' => ['order_id' => 2103],
        'attempts' => 3,
        'max_attempts' => 3,
        'next_retry_at' => null,
        'last_error' => 'auth_rejected',
        'status' => 'failed',
    ]);
    $unrelated = CourierForwardRetry::query()->create([
        'shipment_id' => $shipment->id,
        'payload' => ['order_id' => 2103],
        'attempts' => 1,
        'max_attempts' => 3,
        'next_retry_at' => null,
        'last_error' => 'shipment_missing',
        'status' => 'failed',
    ]);

    $response = $this->withHeaders(forwardSecretHeaders($user))
        ->postJson('/api/courier/store-forward-secret', [
            'store_forward_secret' => $secret,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.forward_secret_set', true)
        ->assertJsonPath('data.secret_updated', true);

    expect(app(StoreForwardSigner::class)->plain($token->fresh()))->toBe($secret);

    $revived = $exhausted->fresh();
    expect($revived->status)->toBe('pending')
        ->and((int) $revived->attempts)->toBe(0);
    expect($unrelated->fresh()->status)->toBe('failed');

    // The secret-only route must not touch courier accounts.
    expect($account->fresh()->environment)->toBe('sandbox');
});

it('rejects a short or unauthenticated secret', function () {
    $user = forwardSecretUser();
    forwardSecretToken($user, 'stored-license-key');

    $this->withHeaders(forwardSecretHeaders($user))
        ->postJson('/api/courier/store-forward-secret', ['store_forward_secret' => 'too-short'])
        ->assertStatus(422);

    $this->withHeaders(['Authorization' => 'Bearer not-a-real-token', 'Origin' => 'https://lovenest.site'])
        ->postJson('/api/courier/store-forward-secret', ['store_forward_secret' => str_repeat('f', 64)])
        ->assertStatus(401);
});

it('treats an admin-only license response as the store being up', function () {
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, 'stored-license-key');
    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-probe-'.$user->id),
        'is_active' => true,
    ]);
    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '310773222',
        'wc_order_id' => 1939,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);

    Http::fake([
        'https://lovenest.site/wp-json/wooeasylife/v1/license-status' => Http::response(['code' => 'rest_forbidden'], 401),
    ]);

    $probe = app(WordPressCourierForwarder::class)->probeReachability($shipment, $token);

    expect($probe['forward_probe_status'])->toBe('awaiting_secret')
        ->and($probe['forward_reachable'])->toBeTrue()
        ->and($probe['forward_secret_set'])->toBeFalse();
});

it('stores the forward secret from webhook sync and releases pending retries', function () {
    $user = forwardSecretUser();
    $token = forwardSecretToken($user, 'stored-license-key');
    $secret = str_repeat('b', 64);
    $account = CourierAccount::query()->create([
        'user_id' => $user->id,
        'partner' => 'steadfast',
        'environment' => 'live',
        'credential_hash' => hash('sha256', 'cred-sync-'.$user->id),
        'is_active' => true,
    ]);
    $shipment = CourierShipment::query()->create([
        'partner' => 'steadfast',
        'environment' => 'live',
        'consignment_id' => '311166922',
        'wc_order_id' => 2029,
        'user_id' => $user->id,
        'access_token_id' => $token->id,
        'site_url' => 'https://lovenest.site',
        'site_domain' => 'lovenest.site',
        'courier_account_id' => $account->id,
        'status' => 'pending',
    ]);
    $retry = CourierForwardRetry::query()->create([
        'shipment_id' => $shipment->id,
        'payload' => ['order_id' => 2029],
        'attempts' => 1,
        'max_attempts' => 3,
        'next_retry_at' => now()->addHour(),
        'status' => 'pending',
    ]);

    $request = Request::create('/api/courier/webhook-sync', 'POST', [
        'partner' => 'steadfast',
        'environment' => 'live',
        'site_url' => 'https://lovenest.site',
        'store_forward_secret' => $secret,
        'webhook_auth' => ['bearer_token' => 'steadfast-api-key'],
    ]);
    $request->headers->set('Authorization', 'Bearer bearer-'.$user->id);

    $result = app(CourierWebhookSyncService::class)->sync($request);

    expect($result['forward_secret_set'])->toBeTrue();
    expect(app(StoreForwardSigner::class)->plain($token->fresh()))->toBe($secret);
    expect($retry->fresh()->next_retry_at->lessThanOrEqualTo(now()->addMinute()))->toBeTrue();
});
