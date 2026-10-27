<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use App\Models\PlatformSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

/**
 * The every-minute bulk checks: numbers whose page was closed, and couriers
 * a number is still waiting for. The switch is saved in Settings. Run now
 * starts one pass without waiting for the server clock.
 */
class BulkFraudScheduler
{
    public const SETTING_KEY = 'fraud_check.scheduler';

    private const HEARTBEAT_KEY = 'fraud_check.scheduler:heartbeat';

    private const RUNNING_KEY = 'fraud_check.scheduler:running';

    public function enabled(): bool
    {
        $stored = $this->stored();

        return $stored === null || ! empty($stored['enabled']);
    }

    public function setEnabled(bool $enabled): void
    {
        if (! $this->tableReady()) {
            throw ValidationException::withMessages([
                'enabled' => 'Platform settings table is not available. Run migrations first.',
            ]);
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => ['enabled' => $enabled]],
        );
    }

    public function allows(bool $manual): bool
    {
        return $manual || $this->enabled();
    }

    public function beat(): void
    {
        try {
            Cache::put(self::HEARTBEAT_KEY, now()->toIso8601String(), now()->addDay());
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));
        }
    }

    public function finish(): void
    {
        try {
            Cache::forget(self::RUNNING_KEY);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * Start one pass of both background checks and return without waiting.
     */
    public function start(): void
    {
        $this->beat();

        try {
            if (! Cache::add(self::RUNNING_KEY, now()->toIso8601String(), 120)) {
                return;
            }
        } catch (\Throwable $exception) {
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));

            return;
        }

        if (app()->runningUnitTests()) {
            return;
        }

        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));
        $command = "({$php} {$artisan} fraud-check:advance-pending --seconds=45 --manual; {$php} {$artisan} fraud-check:fill-waiting --seconds=45 --manual) > /dev/null 2>&1 &";

        try {
            Process::fromShellCommandline($command, base_path())->disableOutput()->start();
        } catch (\Throwable $exception) {
            $this->finish();
            LogHelper::saveLog('Merchant bulk fraud check error', CourierTransport::safeMessage($exception));
        }
    }

    /**
     * @return array{enabled: bool, alive: bool, running: bool, last_ran_at: string|null}
     */
    public function status(): array
    {
        $seen = $this->lastRun();

        return [
            'enabled' => $this->enabled(),
            'alive' => $seen !== null && $seen->greaterThan(now()->subMinutes(3)),
            'running' => $this->running(),
            'last_ran_at' => $seen?->timezone('Asia/Dhaka')->format('d M, h:i:s A'),
        ];
    }

    private function lastRun(): ?Carbon
    {
        try {
            $raw = Cache::get(self::HEARTBEAT_KEY);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function running(): bool
    {
        try {
            return Cache::has(self::RUNNING_KEY);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(): ?array
    {
        if (! $this->tableReady()) {
            return null;
        }

        $value = PlatformSetting::query()->where('key', self::SETTING_KEY)->value('value');

        return is_array($value) ? $value : null;
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
