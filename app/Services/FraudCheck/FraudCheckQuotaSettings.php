<?php

namespace App\Services\FraudCheck;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin-panel overrides for the public hub fraud-check limit and the plugin
 * daily free limit. .env stays the fallback when a field is not saved here.
 */
class FraudCheckQuotaSettings
{
    public const SETTING_KEY = 'fraud_check.daily_limits';

    /**
     * @var array<string, array{config: string, min: int, max: int, label: string, help: string}>
     */
    public const FIELDS = [
        'landing_daily_free_limit' => [
            'config' => 'landing.fraud_check.daily_free_limit',
            'min' => 1,
            'max' => 1000,
            'label' => 'Hub page daily free checks',
            'help' => 'Free phone checks one visitor can run per day on the public hub page. The admin Phone Check page is not limited by this.',
        ],
        'plugin_daily_free_limit' => [
            'config' => 'fraud_check.plugin_free_checks_without_steadfast',
            'min' => 1,
            'max' => 1000,
            'label' => 'Plugin daily free checks',
            'help' => 'Checks a plugin store can run per day when it has not saved its own Steadfast API key and secret key. Stores with their own keys, and whitelisted domains, stay unlimited.',
        ],
    ];

    /**
     * @var array<string, int>|null
     */
    private static ?array $originalDefaults = null;

    private static bool $overridesApplied = false;

    private static ?bool $tableReady = null;

    public function applyOverrides(): void
    {
        // Capture .env defaults once, before any DB override is written into config.
        // Re-read the database on every call so a saved limit applies on the next
        // request. PHP-FPM and `php artisan serve` keep static flags alive.
        $this->captureOriginalDefaults();

        if (! $this->tableReady()) {
            return;
        }

        foreach ($this->storedOverrides() as $field => $value) {
            Config::set(self::FIELDS[$field]['config'], $value);
        }

        self::$overridesApplied = true;
    }

    public static function clearMemoForTests(): void
    {
        self::$overridesApplied = false;
        self::$tableReady = null;
        self::$originalDefaults = null;
    }

    /**
     * @return array{
     *     values: array<string, int>,
     *     defaults: array<string, int>,
     *     overrides: array<string, int>,
     *     fields: array<string, array<string, mixed>>
     * }
     */
    public function snapshot(): array
    {
        $this->applyOverrides();

        $defaults = $this->envDefaults();
        $overrides = $this->storedOverrides();
        $values = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $values[$field] = array_key_exists($field, $overrides)
                ? $overrides[$field]
                : $defaults[$field];
        }

        return [
            'values' => $values,
            'defaults' => $defaults,
            'overrides' => $overrides,
            'fields' => self::FIELDS,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     values: array<string, int>,
     *     defaults: array<string, int>,
     *     overrides: array<string, int>,
     *     fields: array<string, array<string, mixed>>
     * }
     */
    public function update(array $input): array
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'config' => 'Platform settings table is not available. Run migrations first.',
            ]);
        }

        $this->captureOriginalDefaults();
        $defaults = $this->envDefaults();
        $overrides = $this->storedOverrides();

        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $this->normalizeInt($field, $input[$field], $meta);

            if ($value === $defaults[$field]) {
                unset($overrides[$field]);
                Config::set($meta['config'], $defaults[$field]);

                continue;
            }

            $overrides[$field] = $value;
            Config::set($meta['config'], $value);
        }

        $this->persist($overrides);
        self::$overridesApplied = true;

        return $this->snapshot();
    }

    /**
     * @return array{
     *     values: array<string, int>,
     *     defaults: array<string, int>,
     *     overrides: array<string, int>,
     *     fields: array<string, array<string, mixed>>
     * }
     */
    public function resetToEnv(): array
    {
        if ($this->tableReady()) {
            PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
        }

        foreach ($this->envDefaults() as $field => $value) {
            Config::set(self::FIELDS[$field]['config'], $value);
        }

        self::$overridesApplied = true;

        return $this->snapshot();
    }

    /**
     * @return array<string, int>
     */
    private function envDefaults(): array
    {
        $this->captureOriginalDefaults();

        return self::$originalDefaults ?? [];
    }

    private function captureOriginalDefaults(): void
    {
        if (self::$originalDefaults !== null) {
            return;
        }

        $defaults = [];
        foreach (self::FIELDS as $field => $meta) {
            $defaults[$field] = max($meta['min'], (int) config($meta['config'], $meta['min']));
        }

        self::$originalDefaults = $defaults;
    }

    /**
     * @return array<string, int>
     */
    private function storedOverrides(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();
        $value = $row?->value;
        if (! is_array($value)) {
            return [];
        }

        $overrides = [];
        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $value)) {
                continue;
            }

            $int = $this->storedInt($value[$field], $meta);
            if ($int === null) {
                continue;
            }

            $overrides[$field] = $int;
        }

        return $overrides;
    }

    /**
     * @param  array<string, int>  $overrides
     */
    private function persist(array $overrides): void
    {
        if ($overrides === []) {
            PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();

            return;
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $overrides],
        );
    }

    /**
     * Ignore a corrupt saved value instead of failing every request.
     *
     * @param  array{min: int, max: int}  $meta
     */
    private function storedInt(mixed $value, array $meta): ?int
    {
        if (! is_numeric($value) || (is_string($value) && trim($value) === '')) {
            return null;
        }

        $int = (int) $value;
        if ($int < (int) $meta['min'] || $int > (int) $meta['max']) {
            return null;
        }

        return $int;
    }

    /**
     * @param  array{min: int, max: int, label: string}  $meta
     */
    private function normalizeInt(string $field, mixed $value, array $meta): int
    {
        if (! is_numeric($value) || (is_string($value) && trim($value) === '')) {
            throw ValidationException::withMessages([
                $field => "{$meta['label']} must be a whole number.",
            ]);
        }

        $int = (int) $value;
        $min = (int) $meta['min'];
        $max = (int) $meta['max'];

        if ($int < $min || $int > $max) {
            throw ValidationException::withMessages([
                $field => "{$meta['label']} must be between {$min} and {$max}.",
            ]);
        }

        return $int;
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
