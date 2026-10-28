<?php

use App\Models\CourierConfiguration;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Website;
use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// A portal fallback must never reach the real Steadfast and log in there.
beforeEach(fn () => Http::preventStrayRequests());

it('uses steadfast api keys saved in the admin panel before env', function () {
    config([
        'fraud-checker-bd-courier.steadfast.api_key' => 'env-api-key',
        'fraud-checker-bd-courier.steadfast.secret_key' => 'env-secret-key',
    ]);

    app(SteadfastFraudApiCredentialStore::class)->put('admin-api-key', 'admin-secret-key');

    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 80,
            'cancellation_ratio' => 19,
            'delivered_count' => 8,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['api_success'] ?? false)->toBeTrue();
    expect($report['success_rate'])->toBe('80%');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/fraud_check/score/01712345678')
            && $request->hasHeader('Api-Key', 'admin-api-key')
            && $request->hasHeader('Secret-Key', 'admin-secret-key');
    });
});

it('uses the store api keys instead of the admin keys', function () {
    app(SteadfastFraudApiCredentialStore::class)->put('admin-api-key', 'admin-secret-key');
    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 90,
            'cancellation_ratio' => 9,
            'delivered_count' => 9,
            'cancelled_count' => 1,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $report = app(SteadfastFraudChecker::class)->check('01712345678', [
        'api_key' => 'store-api-key',
        'secret_key' => 'store-secret-key',
    ]);

    expect($report['api_success'] ?? false)->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/fraud_check/score/01712345678')
            && $request->hasHeader('Api-Key', 'store-api-key')
            && $request->hasHeader('Secret-Key', 'store-secret-key');
    });
});

it('uses admin api keys when the plugin store has no api key pair', function () {
    app(SteadfastFraudApiCredentialStore::class)->put('admin-api-key', 'admin-secret-key');
    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 70,
            'cancellation_ratio' => 29,
            'delivered_count' => 7,
            'cancelled_count' => 3,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $report = app(SteadfastFraudChecker::class)->check('01712345678', [
        'username' => 'merchant@steadfast.test',
        'password' => 'portal-password',
    ]);

    expect($report['api_success'] ?? false)->toBeTrue();
    expect($report['success_rate'])->toBe('70%');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/fraud_check/score/01712345678')
            && $request->hasHeader('Api-Key', 'admin-api-key')
            && $request->hasHeader('Secret-Key', 'admin-secret-key');
    });
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
});

it('falls back to the portal when the score api rejects the key', function () {
    config([
        'fraud-checker-bd-courier.steadfast.api_key' => 'bad-api-key',
        'fraud-checker-bd-courier.steadfast.secret_key' => 'bad-secret-key',
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
    ]);

    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response(['message' => 'Unauthenticated'], 401);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'GET') {
            return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'POST') {
            return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
        }

        if (str_contains($request->url(), '/user/frauds/check/')) {
            return Http::response([
                'delivery_ratio' => 78,
                'cancellation_ratio' => 21,
                'volume_band' => 'high',
                'volume_range' => '25+',
                'fraud_reports' => 0,
                'fraud_categories' => [],
                'frauds' => [],
                'delivered_count' => 26,
                'cancelled_count' => 7,
            ], 200);
        }

        return Http::response('', 404);
    });

    $report = app(SteadfastFraudChecker::class)->check('01752052358');

    expect($report['credential_error'] ?? false)->toBeFalse();
    expect($report['confirmed'])->toBe(26);
    expect($report['cancel'])->toBe(7);
    expect($report['success_rate'])->toBe('78%');
    expect($report['api_success'] ?? false)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/user/frauds/check/01752052358'));
});

it('tries the next saved steadfast api key when the first one is rejected', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('rejected-api-key', 'rejected-secret', 'Shop 1');
    $store->add('working-api-key', 'working-secret', 'Shop 2');

    Cache::flush();

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        if ($request->hasHeader('Api-Key', 'rejected-api-key')) {
            return Http::response(['message' => 'Unauthenticated'], 401);
        }

        return Http::response([
            'status' => 200,
            'delivery_ratio' => 91,
            'cancellation_ratio' => 8,
            'delivered_count' => 10,
            'cancelled_count' => 1,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['api_success'] ?? false)->toBeTrue();
    expect($report['success_rate'])->toBe('91%');
    expect($report['credential_error'] ?? false)->toBeFalse();

    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'working-api-key'));

    $accounts = collect($store->accounts());
    $rejected = $accounts->firstWhere('label', 'Shop 1');
    $working = $accounts->firstWhere('label', 'Shop 2');

    expect($working['last_error'])->toBe('');

    $sentRejected = collect(Http::recorded())->contains(
        fn ($pair) => $pair[0]->hasHeader('Api-Key', 'rejected-api-key')
    );

    if ($sentRejected) {
        expect($rejected['last_error'])->toBe('Steadfast rejected this API key or secret.');
    }
});

it('skips a steadfast key that already failed when another saved key is healthy', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('broken-api-key', 'broken-secret', 'Broken');
    $store->add('healthy-api-key', 'healthy-secret', 'Healthy');
    $store->recordOutcome('broken-api-key', 'Steadfast rejected this API key or secret.');

    Cache::flush();

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        return Http::response([
            'status' => 200,
            'delivery_ratio' => 70,
            'cancellation_ratio' => 10,
            'delivered_count' => 7,
            'cancelled_count' => 1,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['api_success'] ?? false)->toBeTrue();
    expect($report['success_rate'])->toBe('70%');

    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'healthy-api-key'));
    Http::assertNotSent(fn ($request) => $request->hasHeader('Api-Key', 'broken-api-key'));

    $accounts = collect($store->accounts());
    expect($accounts->firstWhere('label', 'Healthy')['use_count'])->toBe(1);
    expect($accounts->firstWhere('label', 'Broken')['use_count'])->toBe(0);
    expect($accounts->firstWhere('label', 'Broken')['last_error'])->not->toBe('');
});

it('keeps the saved steadfast secret when the admin form leaves it blank', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->put('admin-api-key', 'admin-secret-key');
    $store->put('replaced-api-key', null);

    expect($store->pair())->toBe([
        'api_key' => 'replaced-api-key',
        'secret_key' => 'admin-secret-key',
    ]);
});

it('shows saved steadfast api keys and their secrets', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-steadfast-api@example.com',
        'phone' => '01700000999',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('visible-api-key', 'visible-secret-key', 'Main shop');

    $this->actingAs($admin)
        ->get(route('frauds.steadfastApi'))
        ->assertOk()
        ->assertSee('visible-api-key')
        ->assertSee('visible-secret-key')
        ->assertSee('Main shop');

    $this->actingAs($admin)
        ->post(route('frauds.steadfastApi.store'), [
            'label' => 'Backup shop',
            'api_key' => 'second-api-key',
            'secret_key' => 'second-secret-key',
        ])
        ->assertRedirect();

    $accounts = $store->accounts();

    expect($accounts)->toHaveCount(2);
    expect($accounts[1]['label'])->toBe('Backup shop');
    expect($accounts[1]['secret_key'])->toBe('second-secret-key');
});

it('imports every merchant steadfast api pair with the merchant name and domain', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-import@example.com',
        'phone' => '01700000888',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $shop = User::create([
        'name' => 'Rupa Shop',
        'email' => 'rupa@example.com',
        'phone' => '01700000881',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    Website::create([
        'user_id' => $shop->id,
        'domain' => 'rupa.example.com',
        'title' => 'Rupa',
        'status' => true,
        'is_primary' => true,
    ]);

    Website::create([
        'user_id' => $shop->id,
        'domain' => 'rupa-old.example.com',
        'title' => 'Old',
        'status' => true,
        'is_primary' => false,
    ]);

    CourierConfiguration::create([
        'user_id' => $shop->id,
        'title' => 'Steadfast old',
        'slug' => 'steadfast',
        'api_key' => 'old-rupa-api',
        'secret_key' => 'old-rupa-secret',
        'is_active' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $shop->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'rupa-api',
        'secret_key' => 'rupa-secret',
        'is_active' => true,
    ]);

    $empty = User::create([
        'name' => 'No Key Shop',
        'email' => 'nokey@example.com',
        'phone' => '01700000882',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $empty->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => '',
        'secret_key' => '',
        'is_active' => true,
    ]);

    $saved = User::create([
        'name' => 'Already Saved',
        'email' => 'saved@example.com',
        'phone' => '01700000883',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    Website::create([
        'user_id' => $saved->id,
        'domain' => 'saved.example.com',
        'title' => 'Saved',
        'status' => true,
        'is_primary' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $saved->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'already-api',
        'secret_key' => 'already-secret',
        'is_active' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('already-api', 'already-secret', 'Manual');

    $this->actingAs($admin)
        ->post(route('frauds.steadfastApi.importMerchants'))
        ->assertRedirect()
        ->assertSessionHas('success', '1 merchant key added. 1 already in the list. 1 merchant has no Steadfast API pair.');

    $accounts = $store->accounts();

    expect($accounts)->toHaveCount(2);
    expect($accounts[0]['label'])->toBe('Manual');
    expect($accounts[1]['label'])->toBe('Rupa Shop · rupa.example.com, rupa-old.example.com');
    expect($accounts[1]['api_key'])->toBe('rupa-api');
    expect($accounts[1]['secret_key'])->toBe('rupa-secret');
});

it('imports only new or changed merchant steadfast keys on a second click', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-reimport@example.com',
        'phone' => '01700000666',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $shop = User::create([
        'name' => 'Rupa Shop',
        'email' => 'rupa-reimport@example.com',
        'phone' => '01700000661',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    Website::create([
        'user_id' => $shop->id,
        'domain' => 'rupa-reimport.example.com',
        'title' => 'Rupa',
        'status' => true,
        'is_primary' => true,
    ]);

    $config = CourierConfiguration::create([
        'user_id' => $shop->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'rupa-reimport-api',
        'secret_key' => 'rupa-reimport-secret',
        'is_active' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('manual-keep-api', 'manual-keep-secret', 'Keep me');
    $store->importMerchantKeys();
    $store->recordOutcome('rupa-reimport-api', 'Steadfast rejected this API key or secret.');

    $config->update([
        'secret_key' => 'rupa-reimport-secret-new',
    ]);

    $extra = User::create([
        'name' => 'New Shop',
        'email' => 'new-shop@example.com',
        'phone' => '01700000662',
        'password' => Hash::make('password'),
        'role' => 'user',
        'status' => true,
    ]);

    Website::create([
        'user_id' => $extra->id,
        'domain' => 'new-shop.example.com',
        'title' => 'New',
        'status' => true,
        'is_primary' => true,
    ]);

    CourierConfiguration::create([
        'user_id' => $extra->id,
        'title' => 'Steadfast',
        'slug' => 'steadfast',
        'api_key' => 'new-shop-api',
        'secret_key' => 'new-shop-secret',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('frauds.steadfastApi.importMerchants'))
        ->assertRedirect()
        ->assertSessionHas('success', '1 merchant key added. 1 key updated.');

    $accounts = collect($store->accounts());

    expect($accounts)->toHaveCount(3);
    expect($accounts->firstWhere('label', 'Keep me')['secret_key'])->toBe('manual-keep-secret');

    $rupa = $accounts->firstWhere('source_user_id', $shop->id);
    expect($rupa['api_key'])->toBe('rupa-reimport-api');
    expect($rupa['secret_key'])->toBe('rupa-reimport-secret-new');
    expect($rupa['last_error'])->toBe('Steadfast rejected this API key or secret.');

    $fresh = $accounts->firstWhere('label', 'New Shop · new-shop.example.com');
    expect($fresh['api_key'])->toBe('new-shop-api');
    expect($fresh['last_error'])->toBe('');
});

it('shows a steadfast key error on that key after a hub check rejects it', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-key-error@example.com',
        'phone' => '01700000777',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('broken-api-key', 'broken-secret', 'Broken shop');

    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response(['message' => 'Unauthenticated'], 401);
        }

        return Http::response('', 404);
    });

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect($store->accounts()[0]['last_error'])->toBe('Steadfast rejected this API key or secret.');

    $this->actingAs($admin)
        ->get(route('frauds.steadfastApi'))
        ->assertOk()
        ->assertSee('Broken shop')
        ->assertSee('Steadfast rejected this API key or secret.')
        ->assertSee('broken-api-key')
        ->assertSee('broken-secret');

    expect($store->accounts()[0]['use_count'])->toBe(0);
});

it('clears a steadfast key error when the admin test succeeds', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-key-test@example.com',
        'phone' => '01700000555',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('fixed-api-key', 'fixed-secret', 'Fixed shop');
    $store->recordOutcome('fixed-api-key', 'Steadfast rejected this API key or secret.');

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 50,
            'cancellation_ratio' => 10,
            'delivered_count' => 5,
            'cancelled_count' => 1,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $this->actingAs($admin)
        ->post(route('frauds.steadfastApi.test', $store->accounts()[0]['id']))
        ->assertRedirect()
        ->assertSessionHas('success', 'This key works again. Hub checks can use it.');

    $account = $store->accounts()[0];

    expect($account['last_error'])->toBe('');
    expect($account['use_count'])->toBe(0);
});

it('updates one steadfast api key and leaves the other key alone', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-edit-key@example.com',
        'phone' => '01700000444',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('edit-api-key', 'edit-secret', 'Edit me');
    $store->add('keep-api-key', 'keep-secret', 'Keep me');
    $store->recordOutcome('edit-api-key', 'Steadfast rejected this API key or secret.');

    $editable = collect($store->accounts())->firstWhere('label', 'Edit me');

    $this->actingAs($admin)
        ->put(route('frauds.steadfastApi.updateAccount', $editable['id']), [
            'label' => 'Edited shop',
            'api_key' => 'edit-api-key',
            'secret_key' => 'edit-secret-new',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Steadfast API key saved.');

    $accounts = collect($store->accounts());
    $edited = $accounts->firstWhere('id', $editable['id']);
    $kept = $accounts->firstWhere('label', 'Keep me');

    expect($edited['label'])->toBe('Edited shop');
    expect($edited['api_key'])->toBe('edit-api-key');
    expect($edited['secret_key'])->toBe('edit-secret-new');
    expect($edited['last_error'])->toBe('Steadfast rejected this API key or secret.');
    expect($edited['use_count'])->toBe(0);
    expect($kept['secret_key'])->toBe('keep-secret');

    $this->actingAs($admin)
        ->put(route('frauds.steadfastApi.updateAccount', $editable['id']), [
            'label' => 'Edited shop',
            'api_key' => 'keep-api-key',
            'secret_key' => 'edit-secret-new',
        ])
        ->assertSessionHasErrors('api_key');
});

it('falls back to platform hub steadfast api keys when a store api key is rejected or hides counts', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('hub-active-key', 'hub-active-secret', 'Active Hub Key');

    Cache::flush();

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        if ($request->hasHeader('Api-Key', 'store-inactive-key')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 85,
                'cancellation_ratio' => 15,
                'delivered_count' => null,
                'cancelled_count' => null,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200);
        }

        if ($request->hasHeader('Api-Key', 'hub-active-key')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 85,
                'cancellation_ratio' => 15,
                'delivered_count' => 17,
                'cancelled_count' => 3,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200);
        }

        return Http::response(['message' => 'Unauthenticated'], 401);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678', [
        'api_key' => 'store-inactive-key',
        'secret_key' => 'store-inactive-secret',
    ]);

    expect($report['api_success'] ?? false)->toBeTrue()
        ->and($report['confirmed'])->toBe(17)
        ->and($report['cancel'])->toBe(3)
        ->and($report['total_order'])->toBe(20)
        ->and($report['counts_hidden'])->toBeFalse();
});

it('keeps looking for steadfast counts when a hidden-count key also has fraud notes', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('hub-count-key', 'hub-count-secret', 'Counts');

    Cache::flush();

    Http::fake(function ($request) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        if ($request->hasHeader('Api-Key', 'store-hidden-key')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 70,
                'cancellation_ratio' => 30,
                'delivered_count' => null,
                'cancelled_count' => null,
                'total_reports' => 1,
                'fraud_categories' => ['fake_order' => 1],
            ], 200);
        }

        if ($request->hasHeader('Api-Key', 'hub-count-key')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 70,
                'cancellation_ratio' => 30,
                'delivered_count' => 7,
                'cancelled_count' => 3,
                'total_reports' => 1,
                'fraud_categories' => ['fake_order' => 1],
            ], 200);
        }

        return Http::response(['message' => 'Unauthenticated'], 401);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678', [
        'api_key' => 'store-hidden-key',
        'secret_key' => 'store-hidden-secret',
    ]);

    expect($report['confirmed'])->toBe(7)
        ->and($report['cancel'])->toBe(3)
        ->and($report['counts_hidden'])->toBeFalse();
});

it('rests a rate limited steadfast key instead of turning it off', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('limited-api-key', 'limited-secret', 'Limited shop');

    Cache::flush();

    $calls = 0;

    Http::fake(function ($request) use (&$calls) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        $calls++;

        if ($calls === 1) {
            return Http::response(['message' => 'Too many requests'], 429);
        }

        return Http::response([
            'status' => 200,
            'delivery_ratio' => 80,
            'cancellation_ratio' => 10,
            'delivered_count' => 8,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['rate_limited'] ?? false)->toBeTrue();
    expect($store->accounts()[0]['last_error'])->toBe('');
    expect($store->isCoolingDown('limited-api-key'))->toBeTrue();

    app(SteadfastFraudChecker::class)->check('01712345679');
    expect($calls)->toBe(1);

    Cache::flush();

    $again = app(SteadfastFraudChecker::class)->check('01712345670');

    expect($again['confirmed'])->toBe(8);
    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'limited-api-key'));
});

it('uses a steadfast key again when an old rate limit was saved on it', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('resting-api-key', 'resting-secret', 'Old limit');
    plantSteadfastKeyError('Steadfast rate limit reached for this key.');

    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 75,
            'cancellation_ratio' => 10,
            'delivered_count' => 6,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['confirmed'])->toBe(6);
    expect($store->accounts()[0]['last_error'])->toBe('');
    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'resting-api-key'));
});

it('does not turn a steadfast key off when the admin test hits a rate limit', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin-rate-limit@example.com',
        'phone' => '01700000444',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('tested-api-key', 'tested-secret', 'Tested shop');
    plantSteadfastKeyError('Steadfast rate limit reached for this key.');

    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response(['message' => 'Too many requests'], 429),
    ]);

    $this->actingAs($admin)
        ->post(route('frauds.steadfastApi.test', $store->accounts()[0]['id']))
        ->assertRedirect()
        ->assertSessionHas('success', 'Steadfast rate limit on this key. It rests for a few minutes, then checks use it again.');

    expect($store->accounts()[0]['last_error'])->toBe('Steadfast rate limit reached for this key.');
    expect($store->isCoolingDown('tested-api-key'))->toBeTrue();
});

it('retries a steadfast key after an http 500 and clears the error when it answers', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('outage-api-key', 'outage-secret', 'Outage shop');
    plantSteadfastKeyError('Steadfast request failed (HTTP 500).');

    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::sequence()
            ->push(['message' => 'Server error'], 500)
            ->push([
                'status' => 200,
                'delivery_ratio' => 88,
                'cancellation_ratio' => 12,
                'delivered_count' => 15,
                'cancelled_count' => 2,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200),
    ]);

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect($store->accounts()[0]['last_error'])->toBe('Steadfast request failed (HTTP 500).');
    expect($store->isCoolingDown('outage-api-key'))->toBeTrue();

    Cache::flush();

    $report = app(SteadfastFraudChecker::class)->check('01712345679');

    expect($report['confirmed'])->toBe(15);
    expect($store->accounts()[0]['last_error'])->toBe('');
    expect($store->accounts()[0]['use_count'])->toBe(1);
});

it('tries every steadfast api key for parcel counts before logging in', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('key-one', 'secret-one', 'One');
    $store->add('key-two', 'secret-two', 'Two');
    $store->add('key-three', 'secret-three', 'Three');
    $store->add('key-four', 'secret-four', 'Four');

    Cache::flush();

    $calls = 0;

    Http::fake(function ($request) use (&$calls) {
        if (! str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response('', 404);
        }

        $calls++;

        if ($calls < 4) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 80,
                'cancellation_ratio' => 20,
                'delivered_count' => null,
                'cancelled_count' => null,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200);
        }

        return Http::response([
            'status' => 200,
            'delivery_ratio' => 84,
            'cancellation_ratio' => 15,
            'delivered_count' => 11,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200);
    });

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls)->toBe(4);
    expect($report['confirmed'])->toBe(11);
    expect($report['cancel'])->toBe(2);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
});

it('logs in once after every steadfast api key hides parcel counts', function () {
    config([
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('ratio-key-a', 'ratio-secret-a', 'Ratio A');
    $store->add('ratio-key-b', 'ratio-secret-b', 'Ratio B');

    Cache::flush();

    $logins = 0;

    Http::fake(function ($request) use (&$logins) {
        if (str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 70,
                'cancellation_ratio' => 30,
                'delivered_count' => null,
                'cancelled_count' => null,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'GET') {
            return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
        }

        if (str_contains($request->url(), '/login') && $request->method() === 'POST') {
            $logins++;

            return Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
        }

        if (str_contains($request->url(), '/user/frauds/check/')) {
            return Http::response([
                'delivery_ratio' => 78,
                'cancellation_ratio' => 21,
                'delivered_count' => 26,
                'cancelled_count' => 7,
                'frauds' => [],
            ], 200);
        }

        return Http::response('', 404);
    });

    $report = app(SteadfastFraudChecker::class)->check('01752052358');

    expect($logins)->toBe(1);
    expect($report['confirmed'])->toBe(26);
    expect($report['cancel'])->toBe(7);
    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'ratio-key-a'));
    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'ratio-key-b'));
});

it('does not log in again when a steadfast session is already stored', function () {
    config([
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
    ]);

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('ratio-only-key', 'ratio-only-secret', 'Ratio only');

    Cache::flush();

    Cache::put(SteadfastFraudChecker::sessionCacheKeyFor([
        'username' => 'platform@steadfast.test',
        'password' => 'platform-password',
    ]), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'abc123'],
    ], now()->addMinutes(30));

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/fraud_check/score/')) {
            return Http::response([
                'status' => 200,
                'delivery_ratio' => 70,
                'cancellation_ratio' => 30,
                'delivered_count' => null,
                'cancelled_count' => null,
                'total_reports' => 0,
                'fraud_categories' => [],
            ], 200);
        }

        if (str_contains($request->url(), '/user/frauds/check/')) {
            return Http::response([
                'delivery_ratio' => 81,
                'cancellation_ratio' => 18,
                'delivered_count' => 13,
                'cancelled_count' => 3,
                'frauds' => [],
            ], 200);
        }

        return Http::response('login should not run', 500);
    });

    $report = app(SteadfastFraudChecker::class)->check('01752052358');

    expect($report['confirmed'])->toBe(13);
    expect($report['cancel'])->toBe(3);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/login'));
});

it('stops asking steadfast keys when steadfast itself is down and goes to the portal', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('down-key-a', 'down-secret-a', 'A');
    $store->add('down-key-b', 'down-secret-b', 'B');
    $store->add('down-key-c', 'down-secret-c', 'C');

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response('Bad gateway', 502));

    $first = app(SteadfastFraudChecker::class)->check('01712345678');

    expect(count($calls['score']))->toBe(2)
        ->and($first['confirmed'])->toBe(12)
        ->and($calls['login_post'])->toBe(1);

    $second = app(SteadfastFraudChecker::class)->check('01712345679');

    expect(count($calls['score']))->toBe(2)
        ->and($second['confirmed'])->toBe(12)
        ->and($calls['login_post'])->toBe(1);
    expect($store->accounts()[0]['last_error'])->toBe('');
});

it('does not rest a steadfast key when steadfast refuses the number itself', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('number-key-a', 'number-secret-a', 'A');
    $store->add('number-key-b', 'number-secret-b', 'B');

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response(['message' => 'Invalid phone'], 422));

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect(count($calls['score']))->toBe(1);
    expect($store->isCoolingDown('number-key-a'))->toBeFalse()
        ->and($store->isCoolingDown('number-key-b'))->toBeFalse();
    expect(collect($store->accounts())->pluck('last_error')->all())->toBe(['', '']);
});

it('asks the steadfast key that gave parcel counts first', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('ratio-first-a', 'ratio-secret-a', 'A');
    $store->add('counts-key', 'counts-secret', 'Counts');
    $store->add('ratio-first-b', 'ratio-secret-b', 'B');

    Cache::flush();

    $store->noteShape('ratio-first-a', false);
    $store->noteShape('ratio-first-b', false);
    $store->noteShape('counts-key', true);

    $calls = [];
    fakeSteadfastEverywhere($calls, fn ($request) => $request->hasHeader('Api-Key', 'counts-key')
        ? steadfastCountsAnswer(9, 1)
        : steadfastRatioOnlyAnswer());

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['score'])->toBe(['counts-key'])
        ->and($report['confirmed'])->toBe(9);
});

it('stops at the ratio-only steadfast keys once a ratio is in hand and logs in once', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('new-key', 'new-secret', 'New');
    $store->add('known-ratio-a', 'known-secret-a', 'A');
    $store->add('known-ratio-b', 'known-secret-b', 'B');

    Cache::flush();

    $store->noteShape('known-ratio-a', false);
    $store->noteShape('known-ratio-b', false);

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => steadfastRatioOnlyAnswer());

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['score'])->toBe(['new-key'])
        ->and($calls['login_post'])->toBe(1)
        ->and($report['confirmed'])->toBe(12);
    expect($store->shape('new-key'))->toBe('ratio');
});

it('asks a rejected steadfast key again by itself after six hours', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('healed-key', 'healed-secret', 'Healed');
    plantSteadfastKeyError('Steadfast rejected this API key or secret.', now()->subHours(7));

    Cache::flush();

    expect($store->adminState()['accounts'][0]['next_check_at'])->toBe('soon');

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => steadfastCountsAnswer(6, 2));

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['score'])->toBe(['healed-key'])
        ->and($report['confirmed'])->toBe(6);
    expect($store->accounts()[0]['last_error'])->toBe('');
});

it('does not ask a freshly rejected steadfast key again before six hours', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('fresh-rejected-key', 'fresh-rejected-secret', 'Fresh');
    plantSteadfastKeyError('Steadfast rejected this API key or secret.', now()->subHour());

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => steadfastCountsAnswer());

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['score'] ?? [])->toBe([]);
    expect($store->adminState()['accounts'][0]['next_check_at'])->not->toBe('soon')->not->toBeNull();
});

it('asks only one rejected steadfast key per gap across checks', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('old-rejected-a', 'old-secret-a', 'A');
    $store->add('old-rejected-b', 'old-secret-b', 'B');
    plantSteadfastKeyError('Steadfast rejected this API key or secret.', now()->subHours(8), 0);
    plantSteadfastKeyError('Steadfast rejected this API key or secret.', now()->subHours(7), 1);

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response(['message' => 'Unauthenticated'], 401));

    app(SteadfastFraudChecker::class)->check('01712345678');
    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['score'])->toBe(['old-rejected-a']);
    expect($store->adminState()['accounts'][0]['next_check_at'])->not->toBe('soon');
    expect($store->adminState()['accounts'][1]['next_check_at'])->toBe('soon');
});

it('rests a steadfast key longer each time it keeps failing, up to an hour', function () {
    $store = app(SteadfastFraudApiCredentialStore::class);

    Cache::flush();

    $rests = array_map(fn () => $store->rest('flaky-key', 120), range(1, 7));

    expect($rests)->toBe([120, 240, 480, 960, 1920, 3600, 3600]);

    $store->clearRest('flaky-key');

    expect($store->isCoolingDown('flaky-key'))->toBeFalse()
        ->and($store->rest('flaky-key', 120))->toBe(120);
});

it('asks a rejected steadfast key at the next check after the admin saves a new secret', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('fixed-key', 'old-secret', 'Fixed');
    plantSteadfastKeyError('Steadfast rejected this API key or secret.', now());

    Cache::flush();

    $account = $store->accounts()[0];
    $store->updateAccount($account['id'], 'fixed-key', 'new-secret', 'Fixed');

    expect($store->accounts()[0]['last_error'])->toBe('Steadfast rejected this API key or secret.')
        ->and($store->adminState()['accounts'][0]['next_check_at'])->toBe('soon');

    $calls = [];
    fakeSteadfastEverywhere($calls, fn ($request) => $request->hasHeader('Secret-Key', 'new-secret')
        ? steadfastCountsAnswer(5, 1)
        : Http::response(['message' => 'Unauthenticated'], 401));

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['confirmed'])->toBe(5);
    expect($store->accounts()[0]['last_error'])->toBe('');
});

it('rests the store steadfast key after a rate limit instead of asking it on every number', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response(['message' => 'Too many requests'], 429));

    $store = ['api_key' => 'store-limited-key', 'secret_key' => 'store-limited-secret'];

    app(SteadfastFraudChecker::class)->check('01712345678', $store);
    app(SteadfastFraudChecker::class)->check('01712345679', $store);

    expect($calls['score'])->toBe(['store-limited-key']);
    expect(app(SteadfastFraudApiCredentialStore::class)->isCoolingDown('store-limited-key'))->toBeTrue();
});

it('rests a rejected store steadfast key for half an hour and then asks it again', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response(['message' => 'Unauthenticated'], 401));

    $store = ['api_key' => 'store-rejected-key', 'secret_key' => 'store-rejected-secret'];
    $keys = app(SteadfastFraudApiCredentialStore::class);

    app(SteadfastFraudChecker::class)->check('01712345678', $store);

    $this->travel(29)->minutes();
    expect($keys->isCoolingDown('store-rejected-key'))->toBeTrue();

    $this->travel(2)->minutes();
    expect($keys->isCoolingDown('store-rejected-key'))->toBeFalse();

    app(SteadfastFraudChecker::class)->check('01712345679', $store);

    expect($calls['score'])->toBe(['store-rejected-key', 'store-rejected-key']);
});

it('uses the wait time steadfast sends with a rate limit', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('retry-after-key', 'retry-after-secret', 'Retry after');

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => Http::response(['message' => 'Too many requests'], 429, ['Retry-After' => '120']));

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect($store->isCoolingDown('retry-after-key'))->toBeTrue();

    $this->travel(121)->seconds();

    expect($store->isCoolingDown('retry-after-key'))->toBeFalse();
});

it('logs in to the steadfast portal when every key is resting', function () {
    useOnlyPlatformSteadfastPortal();

    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('resting-only-key', 'resting-only-secret', 'Resting');

    Cache::flush();

    $store->coolDown('resting-only-key', 900);

    $calls = [];
    fakeSteadfastEverywhere($calls, fn () => steadfastCountsAnswer());

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['score'] ?? [])->toBe([])
        ->and($calls['login_post'])->toBe(1)
        ->and($report['confirmed'])->toBe(12);
});

it('sends the steadfast login form once and rests after it times out', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        fn () => Http::failedConnection('cURL error 28: Operation timed out after 25001 milliseconds'),
    );

    app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['login_post'])->toBe(1)
        ->and($calls['login_get'])->toBe(1);

    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['login_post'])->toBe(1);
});

it('keeps the steadfast session when the portal has a server error and drops it after three misses', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    Cache::put(platformSteadfastSessionKey(), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'still-good'],
    ], now()->addMinutes(30));

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        null,
        fn () => Http::response('Server error', 500),
    );

    app(SteadfastFraudChecker::class)->check('01712345678');
    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['frauds'])->toBe(2)
        ->and($calls['login_post'] ?? 0)->toBe(0)
        ->and(Cache::has(platformSteadfastSessionKey()))->toBeTrue();

    app(SteadfastFraudChecker::class)->check('01712345670');

    expect($calls['login_post'])->toBe(1);
});

it('rests the steadfast portal after a rate limit instead of asking it for every number', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    Cache::put(platformSteadfastSessionKey(), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'still-good'],
    ], now()->addHours(2));

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        null,
        fn ($request, int $call) => $call <= 2
            ? Http::response(['message' => 'Too many requests'], 429)
            : Http::response(['delivered_count' => 4, 'cancelled_count' => 1, 'frauds' => []], 200),
    );

    $first = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($first['rate_limited'] ?? false)->toBeTrue()
        ->and($first['retry_after'])->toBe(60)
        ->and($calls['frauds'])->toBe(1);

    $this->travel(20)->seconds();
    $resting = app(SteadfastFraudChecker::class)->check('01712345679');

    expect($resting['rate_limited'] ?? false)->toBeTrue()
        ->and($resting['retry_after'])->toBe(40)
        ->and($calls['frauds'])->toBe(1);

    $this->travel(41)->seconds();
    $second = app(SteadfastFraudChecker::class)->check('01712345670');

    expect($second['retry_after'])->toBe(120)
        ->and($calls['frauds'])->toBe(2);

    $this->travel(121)->seconds();
    $answered = app(SteadfastFraudChecker::class)->check('01712345671');

    expect($answered['confirmed'])->toBe(4)
        ->and($calls['frauds'])->toBe(3)
        ->and($calls['login_post'] ?? 0)->toBe(0)
        ->and(Cache::has(platformSteadfastSessionKey()))->toBeTrue();
});

it('rests the steadfast portal for the wait time steadfast sends', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    Cache::put(platformSteadfastSessionKey(), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'still-good'],
    ], now()->addHours(2));

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        null,
        fn () => Http::response(['message' => 'Too many requests'], 429, ['Retry-After' => '90']),
    );

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['retry_after'])->toBe(90);

    $this->travel(89)->seconds();
    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['frauds'])->toBe(1);
});

it('gives the portal wait time when the store key and the portal both rest', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    Cache::put(platformSteadfastSessionKey(), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'still-good'],
    ], now()->addHours(2));
    app(SteadfastFraudApiCredentialStore::class)->coolDown('store-resting-key', 900);

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        null,
        fn () => Http::response(['message' => 'Too many requests'], 429),
    );

    $store = ['api_key' => 'store-resting-key', 'secret_key' => 'store-resting-secret'];
    $report = app(SteadfastFraudChecker::class)->check('01712345678', $store);

    expect($report['rate_limited'] ?? false)->toBeTrue()
        ->and($report['retry_after'])->toBe(60)
        ->and($calls['score'] ?? [])->toBe([]);
});

it('drops the steadfast session and logs in once when the portal shows the login page', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    Cache::put(platformSteadfastSessionKey(), [
        'host' => 'www.steadfast.com.bd',
        'cookies' => ['steadfast_courier_session' => 'expired'],
    ], now()->addMinutes(30));

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        null,
        fn ($request, int $call) => $call === 1
            ? Http::response('<form><input name="email"><input name="password"> Merchant Login</form>', 200)
            : Http::response(['delivered_count' => 4, 'cancelled_count' => 1, 'frauds' => []], 200),
    );

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($calls['login_post'])->toBe(1)
        ->and($report['confirmed'])->toBe(4);
});

it('waits out a steadfast login lockout instead of logging in on every number', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        fn () => Http::response('Too many login attempts. Please try again in a few minutes.', 429),
    );

    $report = app(SteadfastFraudChecker::class)->check('01712345678');

    expect($report['rate_limited'] ?? false)->toBeTrue()
        ->and(\App\Services\Courier\SteadfastPortalSessionClient::loginCooldownActiveFor([
            'username' => 'platform@steadfast.test',
            'password' => 'platform-password',
        ]))->toBeTrue();

    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['login_post'])->toBe(1);
});

it('does not log in again after steadfast rejects the portal password', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        fn () => Http::response('<form><input name="email"><input name="password"> Merchant Login</form>', 200),
    );

    app(SteadfastFraudChecker::class)->check('01712345678');
    app(SteadfastFraudChecker::class)->check('01712345679');

    expect($calls['login_post'])->toBe(1);

    $this->travel(16)->minutes();

    app(SteadfastFraudChecker::class)->check('01712345670');

    expect($calls['login_post'])->toBe(2);
});

it('does not call a merchant portal timeout a credential error', function () {
    useOnlyPlatformSteadfastPortal();

    Cache::flush();

    $calls = [];
    fakeSteadfastEverywhere(
        $calls,
        fn () => Http::response('', 404),
        fn () => Http::failedConnection('cURL error 28: Operation timed out after 25001 milliseconds'),
    );

    $report = app(SteadfastFraudChecker::class)->check('01712345678', [
        'username' => 'platform@steadfast.test',
        'password' => 'platform-password',
    ]);

    expect($report['credential_error'] ?? false)->toBeFalse()
        ->and($calls['login_post'])->toBe(1);
});

it('asks one steadfast key with a saved error by itself and clears it when steadfast answers', function () {
    useOnlyPlatformSteadfastPortal();
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('heal-first-key', 'heal-first-secret', 'First shop');
    $store->add('heal-second-key', 'heal-second-secret', 'Second shop');
    plantSteadfastKeyError('Steadfast request failed (HTTP 500).', now()->subHour(), 0);
    plantSteadfastKeyError('Steadfast rate limit reached for this key.', now()->subMinutes(10), 1);
    Cache::flush();

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 80,
            'cancellation_ratio' => 20,
            'delivered_count' => 8,
            'cancelled_count' => 2,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $this->artisan('fraud-check:retest-steadfast-keys')->assertSuccessful();

    $accounts = $store->accounts();
    expect($accounts[0]['last_error'])->toBe('')
        ->and($accounts[1]['last_error'])->toBe('Steadfast rate limit reached for this key.');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/fraud_check/score/01700000000')
        && $request->hasHeader('Api-Key', 'heal-first-key'));
    Http::assertSentCount(1);

    $this->artisan('fraud-check:retest-steadfast-keys')->assertSuccessful();

    expect($store->accounts()[1]['last_error'])->toBe('Steadfast rate limit reached for this key.');
    Http::assertSentCount(1);
});

it('does not ask a steadfast key that is still resting', function () {
    useOnlyPlatformSteadfastPortal();
    $store = app(SteadfastFraudApiCredentialStore::class);
    $store->add('resting-heal-key', 'resting-heal-secret', 'Resting shop');
    plantSteadfastKeyError('Steadfast request failed (HTTP 500).');
    Cache::flush();
    $store->rest('resting-heal-key', 600, exact: true);

    Http::fake([
        'https://portal.packzy.com/api/v1/fraud_check/score/*' => Http::response([
            'status' => 200,
            'delivery_ratio' => 80,
            'cancellation_ratio' => 20,
            'delivered_count' => 4,
            'cancelled_count' => 1,
            'total_reports' => 0,
            'fraud_categories' => [],
        ], 200),
    ]);

    $this->artisan('fraud-check:retest-steadfast-keys')->assertSuccessful();

    Http::assertNothingSent();
    expect($store->accounts()[0]['last_error'])->toBe('Steadfast request failed (HTTP 500).');

    $this->travel(11)->minutes();

    $this->artisan('fraud-check:retest-steadfast-keys')->assertSuccessful();

    expect($store->accounts()[0]['last_error'])->toBe('');
    Http::assertSentCount(1);
});

function useOnlyPlatformSteadfastPortal(): void
{
    config([
        'fraud-checker-bd-courier.steadfast.user' => 'platform@steadfast.test',
        'fraud-checker-bd-courier.steadfast.password' => 'platform-password',
        'fraud-checker-bd-courier.steadfast.api_key' => '',
        'fraud-checker-bd-courier.steadfast.secret_key' => '',
    ]);
}

function platformSteadfastSessionKey(): string
{
    return SteadfastFraudChecker::sessionCacheKeyFor([
        'username' => 'platform@steadfast.test',
        'password' => 'platform-password',
    ]);
}

/**
 * Every Steadfast host answers from here, so nothing reaches the real one.
 *
 * @param  array<string, mixed>  $calls
 */
function fakeSteadfastEverywhere(array &$calls, callable $score, ?callable $loginPost = null, ?callable $frauds = null): void
{
    Http::fake(function ($request) use (&$calls, $score, $loginPost, $frauds) {
        $url = $request->url();

        if (str_contains($url, '/fraud_check/score/')) {
            $calls['score'][] = $request->header('Api-Key')[0] ?? '';

            return $score($request, count($calls['score']));
        }

        if (str_contains($url, '/login') && $request->method() === 'GET') {
            $calls['login_get'] = ($calls['login_get'] ?? 0) + 1;

            return Http::response('<input type="hidden" name="_token" value="csrf-token">', 200);
        }

        if (str_contains($url, '/login') && $request->method() === 'POST') {
            $calls['login_post'] = ($calls['login_post'] ?? 0) + 1;

            return $loginPost
                ? $loginPost($request, $calls['login_post'])
                : Http::response('ok', 200, ['Set-Cookie' => 'steadfast_courier_session=abc123; path=/']);
        }

        if (str_contains($url, '/user/frauds/check/')) {
            $calls['frauds'] = ($calls['frauds'] ?? 0) + 1;

            return $frauds
                ? $frauds($request, $calls['frauds'])
                : Http::response([
                    'delivery_ratio' => 92,
                    'cancellation_ratio' => 8,
                    'delivered_count' => 12,
                    'cancelled_count' => 1,
                    'frauds' => [],
                ], 200);
        }

        return Http::response('', 404);
    });
}

function steadfastCountsAnswer(int $delivered = 8, int $cancelled = 2)
{
    return Http::response([
        'status' => 200,
        'delivery_ratio' => 80,
        'cancellation_ratio' => 20,
        'delivered_count' => $delivered,
        'cancelled_count' => $cancelled,
        'total_reports' => 0,
        'fraud_categories' => [],
    ], 200);
}

function steadfastRatioOnlyAnswer()
{
    return Http::response([
        'status' => 200,
        'delivery_ratio' => 70,
        'cancellation_ratio' => 30,
        'delivered_count' => null,
        'cancelled_count' => null,
        'total_reports' => 0,
        'fraud_categories' => [],
    ], 200);
}

function plantSteadfastKeyError(string $message, ?\Carbon\CarbonInterface $at = null, int $index = 0): void
{
    $setting = PlatformSetting::query()
        ->where('key', SteadfastFraudApiCredentialStore::SETTING_KEY)
        ->first();

    $value = $setting->value;
    $value['accounts'][$index]['last_error'] = $message;
    $value['accounts'][$index]['last_error_at'] = ($at ?? now()->subDays(5))
        ->timezone('Asia/Dhaka')
        ->format('j M Y, g:i A');
    $setting->value = $value;
    $setting->save();
}
