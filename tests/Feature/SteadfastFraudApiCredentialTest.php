<?php

use App\Models\CourierConfiguration;
use App\Models\User;
use App\Models\Website;
use App\Services\FraudCheck\SteadfastFraudApiCredentialStore;
use App\Services\FraudCheck\SteadfastFraudChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

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
