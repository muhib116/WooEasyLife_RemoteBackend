<?php

namespace App\Services\FraudCheck;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin-panel overrides for merchant bulk fraud-check speed.
 * .env stays the fallback when a field is not saved here.
 */
class BulkFraudRuntimeSettings
{
    public const SETTING_KEY = 'fraud_check.bulk_runtime';

    /**
     * @var array<string, array{config: string, min: int, max: int, label: string, help: string}>
     */
    public const FIELDS = [
        'bulk_workers' => [
            'config' => 'fraud_check.bulk_workers',
            'min' => 1,
            'max' => 5,
            'label' => 'Numbers checked at once',
            'help' => 'How many numbers of one merchant are sent to the couriers at the same time. More is faster. 3 is the usual choice. 5 can make a courier rate-limit the store.',
        ],
        'bulk_incomplete_retries' => [
            'config' => 'fraud_check.bulk_incomplete_retries',
            'min' => 0,
            'max' => 5,
            'label' => 'Incomplete retries',
            'help' => 'A number missing SteadFast, Pathao, or Paperfly shows Waiting, and only that courier is asked again, up to this many times. A try counts only when the courier was really asked. After the last try the number is Incomplete. 0 saves it as Incomplete at once.',
        ],
        'bulk_incomplete_retry_gap_seconds' => [
            'config' => 'fraud_check.bulk_incomplete_retry_gap_seconds',
            'min' => 3,
            'max' => 30,
            'label' => 'Seconds before a failed courier is asked again',
            'help' => 'After a courier fails for a waiting number, wait this long before asking it again. The wait doubles after each try, up to 10 minutes. A courier that had no room is asked as soon as it has room.',
        ],
        'bulk_waiting_max_minutes' => [
            'config' => 'fraud_check.bulk_waiting.max_minutes',
            'min' => 10,
            'max' => 1440,
            'label' => 'Minutes a number may wait for a courier',
            'help' => 'A number still missing a courier after this long is saved as Incomplete. One Pathao account answers about 4 bulk numbers a minute, so 1000 numbers can need over 4 hours.',
        ],
        'pathao_per_minute' => [
            'config' => 'fraud_check.pace.pathao.per_window',
            'min' => 1,
            'max' => 30,
            'label' => 'Pathao lookups per minute, per account',
            'help' => 'Pathao answers about 5 lookups a minute for one account and then asks us to slow down. After that, the account rests at least 65 seconds. With more Pathao accounts, each one gets this many.',
        ],
        'pathao_live_reserve' => [
            'config' => 'fraud_check.pace.pathao.live_reserve',
            'min' => 0,
            'max' => 5,
            'label' => 'Pathao lookups kept for single checks',
            'help' => 'Of the Pathao lookups each minute, bulk leaves this many for single searches, order checks, and Refresh. 0 gives every lookup to bulk. Bulk always keeps at least one.',
        ],
        'paperfly_gap_seconds' => [
            'config' => 'fraud_check.pace.paperfly.bulk_gap_seconds',
            'min' => 0,
            'max' => 30,
            'label' => 'Seconds between bulk Paperfly lookups',
            'help' => 'Paperfly starts failing after a burst of lookups. Bulk asks it at most once every this many seconds. 0 turns the gap off.',
        ],
        'paperfly_pause_minutes' => [
            'config' => 'fraud_check.pace.paperfly.block.pause_minutes',
            'min' => 1,
            'max' => 60,
            'label' => 'Paperfly pause after errors (minutes)',
            'help' => 'After two Paperfly server errors in a row, Paperfly is left alone this long, then asked once. If that fails too, the pause doubles each time, up to 30 minutes or this pause, whichever is longer.',
        ],
        'redx_sample_skip_minutes' => [
            'config' => 'fraud_check.redx_sample.skip_minutes',
            'min' => 0,
            'max' => 240,
            'label' => 'Minutes RedX is skipped after sample answers',
            'help' => 'When RedX returns the same sample 3 times in a row instead of the number\'s history, RedX is not asked for this long. 0 turns this off.',
        ],
        'bulk_missing_courier_retries' => [
            'config' => 'fraud_check.bulk_missing_courier_retries',
            'min' => 0,
            'max' => 3,
            'label' => 'Missing courier retries on the first check',
            'help' => 'During the first check, how many times to ask only the courier that did not answer. This does not rerun the whole check.',
        ],
        'bulk_number_budget_seconds' => [
            'config' => 'fraud_check.bulk_number_budget_seconds',
            'min' => 15,
            'max' => 180,
            'label' => 'Seconds allowed for one number',
            'help' => 'Stop waiting on one number after this many seconds so it cannot hold up the batch.',
        ],
        'bulk_stale_check_seconds' => [
            'config' => 'fraud_check.bulk_stale_check_seconds',
            'min' => 60,
            'max' => 600,
            'label' => 'Seconds before a stuck number is released',
            'help' => 'A number left on Checking longer than this goes back to the queue for the next request.',
        ],
        'courier_retry_pause_after_failures' => [
            'config' => 'fraud_check.courier_retry.pause_after_failures',
            'min' => 1,
            'max' => 10,
            'label' => 'Courier failures before a pause',
            'help' => 'After this many failed retries in a row, that courier is not retried for any number during the cooldown.',
        ],
        'courier_retry_cooldown_seconds' => [
            'config' => 'fraud_check.courier_retry.cooldown_seconds',
            'min' => 10,
            'max' => 600,
            'label' => 'Courier pause length (seconds)',
            'help' => 'How long a courier that keeps failing is left alone before retries start again.',
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
            $defaults[$field] = max($meta['min'], min($meta['max'], (int) config($meta['config'], $meta['min'])));
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
