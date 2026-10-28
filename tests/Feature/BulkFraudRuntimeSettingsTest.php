<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\FraudCheck\BulkFraudRuntimeSettings;
use App\Services\FraudCheck\BulkFraudScheduler;
use App\Services\FraudCheck\CourierPace;
use Illuminate\Support\Facades\Cache;
use App\Services\FraudCheck\MerchantBulkFraudCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    BulkFraudRuntimeSettings::clearMemoForTests();

    config([
        'fraud_check.bulk_workers' => 3,
        'fraud_check.bulk_incomplete_retries' => 5,
        'fraud_check.bulk_incomplete_retry_gap_seconds' => 5,
        'fraud_check.bulk_missing_courier_retries' => 1,
        'fraud_check.bulk_number_budget_seconds' => 60,
        'fraud_check.bulk_stale_check_seconds' => 150,
        'fraud_check.courier_retry.pause_after_failures' => 3,
        'fraud_check.courier_retry.cooldown_seconds' => 60,
        'fraud_check.pace.pathao.per_window' => 5,
        'fraud_check.pace.pathao.live_reserve' => 1,
        'fraud_check.pace.paperfly.bulk_gap_seconds' => 4,
        'fraud_check.pace.paperfly.block.pause_minutes' => 5,
        'fraud_check.redx_sample.skip_minutes' => 30,
        'fraud_check.bulk_waiting.max_minutes' => 360,
    ]);

    app(BulkFraudRuntimeSettings::class)->applyOverrides();
});

afterEach(function () {
    PlatformSetting::query()->where('key', BulkFraudRuntimeSettings::SETTING_KEY)->delete();
    BulkFraudRuntimeSettings::clearMemoForTests();
});

function bulkSettingsAdmin(): User
{
    return User::create([
        'name' => 'Bulk Settings Admin',
        'email' => 'bulk-settings-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

function bulkSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'bulk_workers' => 3,
        'pathao_per_minute' => 5,
        'paperfly_gap_seconds' => 4,
    ], $overrides);
}

it('applies saved bulk settings over the defaults and ignores old hidden ones', function () {
    PlatformSetting::query()->create([
        'key' => BulkFraudRuntimeSettings::SETTING_KEY,
        'value' => ['bulk_incomplete_retries' => 1, 'bulk_waiting_max_minutes' => 20],
    ]);
    BulkFraudRuntimeSettings::clearMemoForTests();

    app(BulkFraudRuntimeSettings::class)->update(bulkSettingsPayload(['bulk_workers' => 2]));

    expect(config('fraud_check.bulk_workers'))->toBe(2)
        ->and(app(MerchantBulkFraudCheck::class)->workers())->toBe(2)
        ->and(config('fraud_check.bulk_incomplete_retries'))->toBe(5)
        ->and(config('fraud_check.bulk_waiting.max_minutes'))->toBe(360)
        ->and(PlatformSetting::query()->where('key', BulkFraudRuntimeSettings::SETTING_KEY)->value('value'))->toBe(['bulk_workers' => 2]);

    BulkFraudRuntimeSettings::clearMemoForTests();
    config(['fraud_check.bulk_workers' => 3]);
    app(BulkFraudRuntimeSettings::class)->applyOverrides();

    expect(config('fraud_check.bulk_workers'))->toBe(2);

    app(BulkFraudRuntimeSettings::class)->resetToEnv();

    expect(config('fraud_check.bulk_workers'))->toBe(3)
        ->and(PlatformSetting::query()->where('key', BulkFraudRuntimeSettings::SETTING_KEY)->exists())->toBeFalse();
});

it('shows only the three speed settings and resets them to default', function () {
    $admin = bulkSettingsAdmin();

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FraudCheck/Limits')
            ->has('bulk.fields', 3)
            ->where('bulk.defaults.bulk_workers', 3)
            ->where('bulk.defaults.pathao_per_minute', 5)
            ->where('bulk.defaults.paperfly_gap_seconds', 4)
            ->where('bulk.overrides', []));

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'pathao_per_minute' => 8,
        'paperfly_gap_seconds' => 6,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(8)
        ->and(config('fraud_check.pace.pathao.live_reserve'))->toBe(1)
        ->and(config('fraud_check.pace.paperfly.bulk_gap_seconds'))->toBe(6)
        ->and(app(CourierPace::class)->status('pathao', ['acct-a'])['per_minute'])->toBe(6.8)
        ->and(app(CourierPace::class)->status('paperfly', ['acct-p'])['per_minute'])->toBe(10.0);

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertInertia(fn ($page) => $page->where('bulk.overrides', ['pathao_per_minute' => 8, 'paperfly_gap_seconds' => 6]));

    foreach ([['pathao_per_minute', 0], ['pathao_per_minute', 11], ['paperfly_gap_seconds', 1], ['bulk_workers', 6]] as [$field, $value]) {
        $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([$field => $value]))
            ->assertSessionHasErrors($field);
    }

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(8);

    $this->actingAs($admin)->post(route('frauds.limits.bulk.reset'))
        ->assertRedirect()
        ->assertSessionHas('success', 'Bulk check settings are back to default.');

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(5)
        ->and(config('fraud_check.pace.paperfly.bulk_gap_seconds'))->toBe(4)
        ->and(config('fraud_check.bulk_workers'))->toBe(3);
});

it('lets an admin save and reset bulk settings without changing daily limits', function () {
    $admin = bulkSettingsAdmin();
    $pluginLimit = config('fraud_check.plugin_free_checks_without_steadfast');

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'bulk_workers' => 2,
    ]))->assertRedirect();

    expect(config('fraud_check.bulk_workers'))->toBe(2)
        ->and(config('fraud_check.plugin_free_checks_without_steadfast'))->toBe($pluginLimit);

    $this->actingAs($admin)->post(route('frauds.limits.bulk.reset'))
        ->assertRedirect();

    expect(config('fraud_check.bulk_workers'))->toBe(3);
});

it('lets an admin turn background checking off and start one pass', function () {
    $admin = bulkSettingsAdmin();
    $scheduler = app(BulkFraudScheduler::class);

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FraudCheck/Limits')
            ->where('scheduler.enabled', true)
            ->where('scheduler.alive', false)
            ->where('scheduler.running', false));

    expect($scheduler->enabled())->toBeTrue()
        ->and($scheduler->status()['alive'])->toBeFalse();

    $this->actingAs($admin)->put(route('frauds.limits.scheduler.update'), [
        'enabled' => false,
    ])->assertRedirect()->assertSessionHas('success');

    expect($scheduler->enabled())->toBeFalse()
        ->and($scheduler->allows(manual: false))->toBeFalse()
        ->and($scheduler->allows(manual: true))->toBeTrue();

    $this->actingAs($admin)->post(route('frauds.limits.scheduler.run'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $status = $scheduler->status();
    expect($status['alive'])->toBeTrue()
        ->and($status['running'])->toBeTrue()
        ->and($status['enabled'])->toBeFalse()
        ->and($status['last_ran_at'])->not->toBeNull();

    $this->actingAs($admin)->put(route('frauds.limits.scheduler.update'), [
        'enabled' => true,
    ])->assertRedirect();

    expect($scheduler->enabled())->toBeTrue();

    Cache::forget('fraud_check.scheduler:heartbeat');
    Cache::forget('fraud_check.scheduler:running');
    PlatformSetting::query()->where('key', BulkFraudScheduler::SETTING_KEY)->delete();
});
