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
        'bulk_incomplete_retries' => 5,
        'bulk_incomplete_retry_gap_seconds' => 5,
        'bulk_missing_courier_retries' => 1,
        'bulk_number_budget_seconds' => 60,
        'bulk_stale_check_seconds' => 150,
        'courier_retry_pause_after_failures' => 3,
        'courier_retry_cooldown_seconds' => 60,
        'bulk_waiting_max_minutes' => 360,
        'pathao_per_minute' => 5,
        'pathao_live_reserve' => 1,
        'paperfly_gap_seconds' => 4,
        'paperfly_pause_minutes' => 5,
        'redx_sample_skip_minutes' => 30,
    ], $overrides);
}

it('applies saved bulk settings over the env defaults', function () {
    $saved = bulkSettingsPayload([
        'bulk_workers' => 2,
        'bulk_incomplete_retries' => 4,
        'bulk_incomplete_retry_gap_seconds' => 8,
    ]);

    app(BulkFraudRuntimeSettings::class)->update($saved);

    expect(config('fraud_check.bulk_workers'))->toBe(2)
        ->and(config('fraud_check.bulk_incomplete_retries'))->toBe(4)
        ->and(config('fraud_check.bulk_incomplete_retry_gap_seconds'))->toBe(8)
        ->and(app(MerchantBulkFraudCheck::class)->workers())->toBe(2)
        ->and(app(MerchantBulkFraudCheck::class)->incompleteRetries())->toBe(4)
        ->and(app(MerchantBulkFraudCheck::class)->incompleteRetryGapSeconds())->toBe(8)
        ->and(PlatformSetting::query()->where('key', BulkFraudRuntimeSettings::SETTING_KEY)->exists())->toBeTrue();

    BulkFraudRuntimeSettings::clearMemoForTests();
    config([
        'fraud_check.bulk_workers' => 3,
        'fraud_check.bulk_incomplete_retries' => 5,
        'fraud_check.bulk_incomplete_retry_gap_seconds' => 5,
    ]);
    app(BulkFraudRuntimeSettings::class)->applyOverrides();

    expect(config('fraud_check.bulk_workers'))->toBe(2)
        ->and(config('fraud_check.bulk_incomplete_retries'))->toBe(4);

    app(BulkFraudRuntimeSettings::class)->resetToEnv();

    expect(config('fraud_check.bulk_workers'))->toBe(3)
        ->and(config('fraud_check.bulk_incomplete_retries'))->toBe(5)
        ->and(PlatformSetting::query()->where('key', BulkFraudRuntimeSettings::SETTING_KEY)->exists())->toBeFalse();
});

it('lets an admin set the courier pace and the waiting time', function () {
    $admin = bulkSettingsAdmin();

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertOk()
        ->assertSee('Pathao lookups per minute, per account')
        ->assertSee('Seconds between bulk Paperfly lookups')
        ->assertSee('Minutes a number may wait for a courier');

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'pathao_per_minute' => 8,
        'pathao_live_reserve' => 0,
        'paperfly_gap_seconds' => 6,
        'paperfly_pause_minutes' => 10,
        'redx_sample_skip_minutes' => 0,
        'bulk_waiting_max_minutes' => 600,
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(8)
        ->and(config('fraud_check.pace.pathao.live_reserve'))->toBe(0)
        ->and(config('fraud_check.pace.paperfly.bulk_gap_seconds'))->toBe(6)
        ->and(config('fraud_check.pace.paperfly.block.pause_minutes'))->toBe(10)
        ->and(config('fraud_check.redx_sample.skip_minutes'))->toBe(0)
        ->and(config('fraud_check.bulk_waiting.max_minutes'))->toBe(600)
        ->and(app(CourierPace::class)->status('pathao', ['acct-a'])['per_minute'])->toBe(7.7)
        ->and(app(CourierPace::class)->status('paperfly', ['acct-p'])['per_minute'])->toBe(10.0);

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'pathao_per_minute' => 0,
    ]))->assertSessionHasErrors('pathao_per_minute');

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(8);

    $this->actingAs($admin)->post(route('frauds.limits.bulk.reset'))->assertRedirect();

    expect(config('fraud_check.pace.pathao.per_window'))->toBe(5)
        ->and(config('fraud_check.pace.pathao.live_reserve'))->toBe(1)
        ->and(config('fraud_check.pace.paperfly.bulk_gap_seconds'))->toBe(4)
        ->and(config('fraud_check.bulk_waiting.max_minutes'))->toBe(360);
});

it('lets an admin save and reset bulk settings without changing daily limits', function () {
    $admin = bulkSettingsAdmin();

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertOk()
        ->assertSee('Incomplete retries')
        ->assertSee('Numbers checked at once');

    $pluginLimit = config('fraud_check.plugin_free_checks_without_steadfast');

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'bulk_workers' => 2,
        'bulk_incomplete_retries' => 5,
    ]))->assertRedirect();

    expect(config('fraud_check.bulk_workers'))->toBe(2)
        ->and(config('fraud_check.plugin_free_checks_without_steadfast'))->toBe($pluginLimit);

    $this->actingAs($admin)->put(route('frauds.limits.bulk.update'), bulkSettingsPayload([
        'bulk_incomplete_retries' => 9,
    ]))->assertSessionHasErrors('bulk_incomplete_retries');

    expect(config('fraud_check.bulk_workers'))->toBe(2);

    $this->actingAs($admin)->post(route('frauds.limits.bulk.reset'))
        ->assertRedirect();

    expect(config('fraud_check.bulk_workers'))->toBe(3)
        ->and(config('fraud_check.bulk_incomplete_retries'))->toBe(5);
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
