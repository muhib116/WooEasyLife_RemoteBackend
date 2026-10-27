<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\FraudCheck\BulkFraudRuntimeSettings;
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
