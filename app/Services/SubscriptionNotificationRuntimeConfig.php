<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class SubscriptionNotificationRuntimeConfig
{
    public const SETTING_KEY = 'subscription.notifications.sms_expiry';

    public const CONFIG_KEY = 'subscription.notifications.sms_expiry';

    private static ?bool $envDefault = null;

    private static bool $overridesApplied = false;

    private static ?bool $tableReady = null;

    public function applyOverrides(): void
    {
        $this->captureEnvDefault();

        $stored = $this->stored();
        if ($stored === null) {
            return;
        }

        Config::set(self::CONFIG_KEY, $stored);
        self::$overridesApplied = true;
    }

    public static function clearMemoForTests(): void
    {
        self::$overridesApplied = false;
        self::$tableReady = null;
        self::$envDefault = null;
    }

    /**
     * @return array{sms_expiry: bool, source: 'database'|'env', env_default: bool}
     */
    public function snapshot(): array
    {
        $this->applyOverrides();

        $stored = $this->stored();

        return [
            'sms_expiry' => (bool) config(self::CONFIG_KEY, true),
            'source' => $stored === null ? 'env' : 'database',
            'env_default' => $this->envDefault(),
        ];
    }

    /**
     * @return array{sms_expiry: bool, source: 'database'|'env', env_default: bool}
     */
    public function update(bool $enabled): array
    {
        if (! $this->tableReady()) {
            Config::set(self::CONFIG_KEY, $enabled);

            return $this->snapshot();
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $enabled],
        );

        Config::set(self::CONFIG_KEY, $enabled);
        self::$overridesApplied = true;

        return $this->snapshot();
    }

    public function envDefault(): bool
    {
        $this->captureEnvDefault();

        return self::$envDefault ?? true;
    }

    private function stored(): ?bool
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();
        if (! $row || $row->value === null || $row->value === '') {
            return null;
        }

        $value = $row->value;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return null;
    }

    private function captureEnvDefault(): void
    {
        if (self::$envDefault !== null) {
            return;
        }

        self::$envDefault = filter_var(env('SUBSCRIPTION_NOTIFY_SMS_EXPIRY', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function tableReady(): bool
    {
        if (self::$tableReady !== null) {
            return self::$tableReady;
        }

        try {
            self::$tableReady = Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            self::$tableReady = false;
        }

        return self::$tableReady;
    }
}
