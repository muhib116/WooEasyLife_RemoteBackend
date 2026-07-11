<?php

namespace App\Services\OrderIntelligence;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FraudCheckRuntimeConfig
{
    public const GROUP = 'order_intelligence.fraud_check';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $originalDefaults = null;

    /**
     * @var array<string, array{type: string, options?: list<string>, min?: int, max?: int, label: string, help: string}>
     */
    public const FIELDS = [
        'mode' => [
            'type' => 'enum',
            'options' => ['hybrid', 'external_only', 'platform_first'],
            'label' => 'Fraud check mode',
            'help' => 'hybrid = serve cache first; external_only = always live; platform_first = prefer any platform signal',
        ],
        'stale_while_revalidate' => [
            'type' => 'bool',
            'label' => 'Stale while revalidate',
            'help' => 'Return cached snapshots immediately and refresh stale/failed couriers in the background',
        ],
        'preserve_snapshot_on_failure' => [
            'type' => 'bool',
            'label' => 'Preserve snapshot on failure',
            'help' => 'Keep last good courier data when a partner fetch fails or is blocked',
        ],
        'partial_refresh' => [
            'type' => 'bool',
            'label' => 'Partial refresh',
            'help' => 'Background refresh only failed/stale couriers instead of all five',
        ],
        'max_snapshot_staleness_hours' => [
            'type' => 'int',
            'min' => 1,
            'max' => 168,
            'label' => 'Snapshot freshness (hours)',
            'help' => 'How long courier snapshots stay fresh before background revalidation',
        ],
        'refresh_unique_for_seconds' => [
            'type' => 'int',
            'min' => 60,
            'max' => 86400,
            'label' => 'Refresh cooldown (seconds)',
            'help' => 'Minimum wait before scheduling another background refresh for the same phone',
        ],
        'min_platform_orders' => [
            'type' => 'int',
            'min' => 0,
            'max' => 1000,
            'label' => 'Min platform orders',
            'help' => 'Minimum platform order count needed for platform-first style sufficiency checks',
        ],
        'debug_trace' => [
            'type' => 'bool',
            'label' => 'Developer debug trail',
            'help' => 'Attach a step-by-step decision log on admin fraud checks and write Laravel debug logs',
        ],
    ];

    public function applyOverrides(): void
    {
        $this->captureOriginalDefaults();

        if (! $this->tableReady()) {
            return;
        }

        foreach ($this->storedOverrides() as $field => $value) {
            Config::set($this->configKey($field), $value);
        }
    }

    /**
     * Effective values for the admin UI (env defaults + DB overrides).
     *
     * @return array{
     *     values: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     overrides: array<string, mixed>,
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
     *     values: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     overrides: array<string, mixed>,
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

        $defaults = $this->envDefaults();

        foreach (self::FIELDS as $field => $meta) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $this->normalizeValue($field, $input[$field]);
            $key = $this->settingKey($field);

            if ($this->valuesEqual($value, $defaults[$field])) {
                PlatformSetting::query()->where('key', $key)->delete();
                Config::set($this->configKey($field), $defaults[$field]);

                continue;
            }

            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
            Config::set($this->configKey($field), $value);
        }

        return $this->snapshot();
    }

    /**
     * @return array{
     *     values: array<string, mixed>,
     *     defaults: array<string, mixed>,
     *     overrides: array<string, mixed>,
     *     fields: array<string, array<string, mixed>>
     * }
     */
    public function resetToEnv(): array
    {
        if ($this->tableReady()) {
            $keys = array_map(fn (string $field) => $this->settingKey($field), array_keys(self::FIELDS));
            PlatformSetting::query()->whereIn('key', $keys)->delete();
        }

        foreach ($this->envDefaults() as $field => $value) {
            Config::set($this->configKey($field), $value);
        }

        return $this->snapshot();
    }

    /**
     * @return array<string, mixed>
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
            $defaults[$field] = $this->castDefault($field, config($this->configKey($field)), $meta);
        }

        self::$originalDefaults = $defaults;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function castDefault(string $field, mixed $value, array $meta): mixed
    {
        return match ($meta['type']) {
            'bool' => (bool) ($value ?? false),
            'int' => (int) ($value ?? ($meta['min'] ?? 0)),
            'enum' => (string) ($value ?? ($meta['options'][0] ?? '')),
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function storedOverrides(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $keys = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $keys[$this->settingKey($field)] = $field;
        }

        $rows = PlatformSetting::query()
            ->whereIn('key', array_keys($keys))
            ->get(['key', 'value']);

        $overrides = [];
        foreach ($rows as $row) {
            $field = $keys[$row->key] ?? null;
            if ($field === null) {
                continue;
            }

            $overrides[$field] = $this->normalizeValue($field, $row->value);
        }

        return $overrides;
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        $meta = self::FIELDS[$field];

        return match ($meta['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'int' => $this->normalizeInt($field, $value, $meta),
            'enum' => $this->normalizeEnum($field, $value, $meta),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function normalizeInt(string $field, mixed $value, array $meta): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => "{$meta['label']} must be a number.",
            ]);
        }

        $int = (int) $value;
        $min = (int) ($meta['min'] ?? PHP_INT_MIN);
        $max = (int) ($meta['max'] ?? PHP_INT_MAX);

        if ($int < $min || $int > $max) {
            throw ValidationException::withMessages([
                $field => "{$meta['label']} must be between {$min} and {$max}.",
            ]);
        }

        return $int;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function normalizeEnum(string $field, mixed $value, array $meta): string
    {
        $string = (string) $value;
        $options = $meta['options'] ?? [];

        if (! in_array($string, $options, true)) {
            throw ValidationException::withMessages([
                $field => "{$meta['label']} must be one of: ".implode(', ', $options),
            ]);
        }

        return $string;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return $left === $right;
    }

    private function settingKey(string $field): string
    {
        return self::GROUP.'.'.$field;
    }

    private function configKey(string $field): string
    {
        return self::GROUP.'.'.$field;
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
