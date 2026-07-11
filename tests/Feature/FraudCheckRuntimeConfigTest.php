<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\OrderIntelligence\FraudCheckRuntimeConfig;
use Illuminate\Support\Facades\Hash;

function createRuntimeConfigAdmin(): User
{
    return User::create([
        'name' => 'Runtime Admin',
        'email' => 'runtime-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

beforeEach(function () {
    $ref = new ReflectionClass(FraudCheckRuntimeConfig::class);
    $prop = $ref->getProperty('originalDefaults');
    $prop->setAccessible(true);
    $prop->setValue(null, null);

    PlatformSetting::query()
        ->where('key', 'like', 'order_intelligence.fraud_check.%')
        ->delete();

    config([
        'order_intelligence.fraud_check.mode' => 'hybrid',
        'order_intelligence.fraud_check.stale_while_revalidate' => true,
        'order_intelligence.fraud_check.preserve_snapshot_on_failure' => true,
        'order_intelligence.fraud_check.partial_refresh' => true,
        'order_intelligence.fraud_check.max_snapshot_staleness_hours' => 5,
        'order_intelligence.fraud_check.refresh_unique_for_seconds' => 900,
        'order_intelligence.fraud_check.min_platform_orders' => 1,
        'order_intelligence.fraud_check.debug_trace' => false,
    ]);

    app(FraudCheckRuntimeConfig::class)->applyOverrides();
});

it('applies runtime fraud check overrides without changing env', function () {
    $service = app(FraudCheckRuntimeConfig::class);

    expect(config('order_intelligence.fraud_check.mode'))->toBe('hybrid');

    $service->update([
        'mode' => 'external_only',
        'max_snapshot_staleness_hours' => 2,
        'stale_while_revalidate' => false,
    ]);

    expect(config('order_intelligence.fraud_check.mode'))->toBe('external_only')
        ->and(config('order_intelligence.fraud_check.max_snapshot_staleness_hours'))->toBe(2)
        ->and(config('order_intelligence.fraud_check.stale_while_revalidate'))->toBeFalse()
        ->and(PlatformSetting::query()->where('key', 'order_intelligence.fraud_check.mode')->exists())->toBeTrue();

    $snapshot = $service->snapshot();
    expect($snapshot['values']['mode'])->toBe('external_only')
        ->and($snapshot['defaults']['mode'])->toBe('hybrid')
        ->and($snapshot['overrides'])->toHaveKey('mode');

    $service->resetToEnv();

    expect(config('order_intelligence.fraud_check.mode'))->toBe('hybrid')
        ->and(PlatformSetting::query()->where('key', 'like', 'order_intelligence.fraud_check.%')->count())->toBe(0);
});

it('requires auth for runtime config endpoints', function () {
    $this->get(route('frauds.runtimeConfig'))->assertRedirect();

    $admin = createRuntimeConfigAdmin();

    $this->actingAs($admin)->get(route('frauds.runtimeConfig'))
        ->assertOk()
        ->assertJsonPath('values.mode', 'hybrid');

    $this->actingAs($admin)->putJson(route('frauds.updateRuntimeConfig'), [
        'mode' => 'platform_first',
        'partial_refresh' => false,
    ])
        ->assertOk()
        ->assertJsonPath('config.values.mode', 'platform_first')
        ->assertJsonPath('config.values.partial_refresh', false);

    expect(config('order_intelligence.fraud_check.mode'))->toBe('platform_first');

    $this->actingAs($admin)->postJson(route('frauds.resetRuntimeConfig'))
        ->assertOk()
        ->assertJsonPath('config.values.mode', 'hybrid');
});

it('includes a decision debug trail for admins only on web routes', function () {
    $this->mock(\App\Services\FraudCheckService::class, function ($mock) {
        $mock->shouldReceive('normalizePhone')->andReturn('01700000000');
        $mock->shouldReceive('getReport')->andReturn([
            'total_order' => 0,
            'confirmed' => 0,
            'cancel' => 0,
            'success_rate' => 'No order history found!',
            'frauds' => [],
            'courier' => [],
        ]);
    });

    $admin = createRuntimeConfigAdmin();

    $response = $this->actingAs($admin)->postJson(route('frauds.adminFraudCheck'), [
        'phone' => '01700000000',
        'debug' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('_debug.enabled', true);

    expect($response->json('_debug.steps'))->toBeArray()->not->toBeEmpty()
        ->and(collect($response->json('_debug.steps'))->pluck('step')->all())
        ->toContain('start', 'cache_lookup', 'decision');
});
