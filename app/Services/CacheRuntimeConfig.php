<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin-selectable default cache store (database|redis|file).
 * Stored in platform_settings; applied early via AppServiceProvider + middleware.
 */
class CacheRuntimeConfig
{
    public const SETTING_KEY = 'app.cache.driver';

    public const CONFIG_KEY = 'cache.default';

    /** @var list<string> */
    public const DRIVERS = ['database', 'redis', 'file'];

    public const DEFAULT_DRIVER = 'database';

    private static ?string $envDefault = null;

    public function applyOverrides(): void
    {
        $this->captureEnvDefault();

        $driver = $this->effectiveDriver();
        Config::set(self::CONFIG_KEY, $driver);
        $this->forgetResolvedCache();
    }

    /**
     * @return array{
     *     driver: string,
     *     env_default: string,
     *     source: 'database'|'env'|'default',
     *     options: list<string>,
     *     redis_configured: bool,
     *     note: string
     * }
     */
    public function snapshot(): array
    {
        $this->applyOverrides();

        $override = $this->storedDriver();
        $envDefault = $this->envDefault();
        $driver = $override ?? $envDefault;

        $source = 'default';
        if ($override !== null) {
            $source = 'database';
        } elseif ($this->envExplicitlySet()) {
            $source = 'env';
        }

        return [
            'driver' => $driver,
            'env_default' => $envDefault,
            'source' => $source,
            'options' => self::DRIVERS,
            'redis_configured' => $this->redisLooksConfigured(),
            'note' => 'Visitors analytics dedupe/quotas require database, redis, or file — not array. Default is database.',
        ];
    }

    /**
     * @return array{
     *     driver: string,
     *     env_default: string,
     *     source: 'database'|'env'|'default',
     *     options: list<string>,
     *     redis_configured: bool,
     *     note: string
     * }
     */
    public function update(string $driver): array
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'driver' => 'Platform settings table is not available. Run migrations first.',
            ]);
        }

        $driver = $this->normalizeDriver($driver);
        $envDefault = $this->envDefault();

        // Matching the resolved env/config default clears the admin override.
        if ($driver === $envDefault) {
            PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
        } else {
            PlatformSetting::query()->updateOrCreate(
                ['key' => self::SETTING_KEY],
                ['value' => $driver],
            );
        }

        Config::set(self::CONFIG_KEY, $driver);
        $this->forgetResolvedCache();

        return $this->snapshot();
    }

    /**
     * @return array{
     *     driver: string,
     *     env_default: string,
     *     source: 'database'|'env'|'default',
     *     options: list<string>,
     *     redis_configured: bool,
     *     note: string
     * }
     */
    public function resetToEnv(): array
    {
        if ($this->tableReady()) {
            PlatformSetting::query()->where('key', self::SETTING_KEY)->delete();
        }

        $driver = $this->envDefault();
        Config::set(self::CONFIG_KEY, $driver);
        $this->forgetResolvedCache();

        return $this->snapshot();
    }

    public function effectiveDriver(): string
    {
        return $this->storedDriver() ?? $this->envDefault();
    }

    private function storedDriver(): ?string
    {
        if (! $this->tableReady()) {
            return null;
        }

        $row = PlatformSetting::query()->where('key', self::SETTING_KEY)->first();
        if (! $row || $row->value === null || $row->value === '') {
            return null;
        }

        $value = is_string($row->value) ? $row->value : (string) $row->value;

        try {
            return $this->normalizeDriver($value);
        } catch (ValidationException) {
            return null;
        }
    }

    private function envDefault(): string
    {
        $this->captureEnvDefault();

        return self::$envDefault ?? self::DEFAULT_DRIVER;
    }

    private function captureEnvDefault(): void
    {
        if (self::$envDefault !== null) {
            return;
        }

        // Prefer raw env so DB overrides don't poison the "env default" baseline.
        $fromEnv = env('CACHE_DRIVER');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidate = strtolower(trim($fromEnv));
            self::$envDefault = in_array($candidate, self::DRIVERS, true)
                ? $candidate
                : self::DEFAULT_DRIVER;

            return;
        }

        self::$envDefault = self::DEFAULT_DRIVER;
    }

    private function envExplicitlySet(): bool
    {
        $fromEnv = env('CACHE_DRIVER');

        return is_string($fromEnv) && $fromEnv !== '';
    }

    private function normalizeDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        if (! in_array($driver, self::DRIVERS, true)) {
            throw ValidationException::withMessages([
                'driver' => 'Cache driver must be one of: '.implode(', ', self::DRIVERS),
            ]);
        }

        return $driver;
    }

    private function forgetResolvedCache(): void
    {
        try {
            app()->forgetInstance('cache');
            app()->forgetInstance('cache.store');
        } catch (\Throwable) {
            // Container may not have bound cache yet during early boot.
        }
    }

    private function redisLooksConfigured(): bool
    {
        $host = (string) config('database.redis.default.host', '');
        $client = (string) config('database.redis.client', '');

        return $host !== '' || $client !== '';
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
