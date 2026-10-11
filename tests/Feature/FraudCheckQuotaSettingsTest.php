<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\FraudCheck\FraudCheckQuotaSettings;
use App\Services\FraudCheck\PluginFraudCheckFreeQuota;
use App\Services\PublicFraudCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    FraudCheckQuotaSettings::clearMemoForTests();

    config([
        'landing.fraud_check.daily_free_limit' => 5,
        'fraud_check.plugin_free_checks_without_steadfast' => 10,
    ]);

    app(FraudCheckQuotaSettings::class)->applyOverrides();
});

afterEach(function () {
    PlatformSetting::query()->where('key', FraudCheckQuotaSettings::SETTING_KEY)->delete();
    FraudCheckQuotaSettings::clearMemoForTests();
});

function createQuotaAdmin(): User
{
    return User::create([
        'name' => 'Quota Admin',
        'email' => 'quota-admin-'.uniqid().'@example.com',
        'phone' => '017'.random_int(10000000, 99999999),
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => true,
    ]);
}

it('applies saved daily limits over the env defaults', function () {
    $service = app(FraudCheckQuotaSettings::class);

    $service->update([
        'landing_daily_free_limit' => 8,
        'plugin_daily_free_limit' => 15,
    ]);

    expect(app(PublicFraudCheckService::class)->dailyFreeLimit())->toBe(8)
        ->and(app(PluginFraudCheckFreeQuota::class)->limit())->toBe(15)
        ->and(PlatformSetting::query()->where('key', FraudCheckQuotaSettings::SETTING_KEY)->exists())->toBeTrue();

    FraudCheckQuotaSettings::clearMemoForTests();
    config([
        'landing.fraud_check.daily_free_limit' => 5,
        'fraud_check.plugin_free_checks_without_steadfast' => 10,
    ]);
    app(FraudCheckQuotaSettings::class)->applyOverrides();

    expect(config('landing.fraud_check.daily_free_limit'))->toBe(8)
        ->and(config('fraud_check.plugin_free_checks_without_steadfast'))->toBe(15);

    $service->resetToEnv();

    expect(config('landing.fraud_check.daily_free_limit'))->toBe(5)
        ->and(config('fraud_check.plugin_free_checks_without_steadfast'))->toBe(10)
        ->and(PlatformSetting::query()->where('key', FraudCheckQuotaSettings::SETTING_KEY)->exists())->toBeFalse();
});

it('applies a saved daily limit again on the next request in the same process', function () {
    app(FraudCheckQuotaSettings::class)->update([
        'landing_daily_free_limit' => 8,
        'plugin_daily_free_limit' => 15,
    ]);

    config([
        'landing.fraud_check.daily_free_limit' => 5,
        'fraud_check.plugin_free_checks_without_steadfast' => 10,
    ]);

    app(FraudCheckQuotaSettings::class)->applyOverrides();

    expect(config('landing.fraud_check.daily_free_limit'))->toBe(8)
        ->and(config('fraud_check.plugin_free_checks_without_steadfast'))->toBe(15);
});

it('drops a saved limit when it matches the env default', function () {
    $snapshot = app(FraudCheckQuotaSettings::class)->update([
        'landing_daily_free_limit' => 5,
        'plugin_daily_free_limit' => 12,
    ]);

    expect($snapshot['overrides'])->not->toHaveKey('landing_daily_free_limit')
        ->and($snapshot['overrides']['plugin_daily_free_limit'])->toBe(12)
        ->and($snapshot['values']['landing_daily_free_limit'])->toBe(5);
});

it('lets an admin save and reset both limits from the panel', function () {
    $this->get(route('frauds.limits'))->assertRedirect();

    $admin = createQuotaAdmin();

    $this->actingAs($admin)->get(route('frauds.limits'))
        ->assertOk()
        ->assertSee('Hub page daily free checks')
        ->assertSee('Plugin daily free checks');

    $this->actingAs($admin)->put(route('frauds.limits.update'), [
        'landing_daily_free_limit' => 7,
        'plugin_daily_free_limit' => 3,
    ])->assertRedirect();

    expect(app(PublicFraudCheckService::class)->dailyFreeLimit())->toBe(7)
        ->and(app(PluginFraudCheckFreeQuota::class)->limit())->toBe(3);

    $this->actingAs($admin)->put(route('frauds.limits.update'), [
        'landing_daily_free_limit' => 0,
        'plugin_daily_free_limit' => 3,
    ])->assertSessionHasErrors('landing_daily_free_limit');

    $this->actingAs($admin)->post(route('frauds.limits.reset'))
        ->assertRedirect();

    expect(app(PublicFraudCheckService::class)->dailyFreeLimit())->toBe(5)
        ->and(app(PluginFraudCheckFreeQuota::class)->limit())->toBe(10);
});
