<?php

namespace App\Services\FraudCheck;

use App\LogHelper;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Shared pace for courier lookups, kept per courier account ("lane").
 *
 * A sliding window caps the calls of a lane. A live order check may use the
 * whole window. Bulk work leaves live_reserve calls free and keeps a gap
 * between its own calls, so a bulk run never takes the last slot.
 *
 * A rate limit rests the lane. When a lane is limited while under its own
 * cap and the courier as a whole was over it, the limit is taken as shared
 * by every account, and the whole courier is counted and rested together.
 *
 * A courier that blocks with server errors (Paperfly) pauses after a few
 * errors in a row, longer each time, and the first call after a pause is a
 * lone probe.
 */
class CourierPace
{
    private const ALL = '*';

    private const MAX_REST_SECONDS = 900;

    private const PROBE_SECONDS = 90;

    private const PROBE_RECHECK_MS = 15000;

    public function __construct(private CourierCallContext $context) {}

    /**
     * Take a call slot on $lane. Waits when the slot frees within the
     * caller's allowance. Returns 0 when the call may go now, otherwise the
     * seconds until it could.
     */
    public function claim(string $courier, string $lane, ?int $maxWaitSeconds = null): int
    {
        $allowanceMs = 1000 * max(0, $maxWaitSeconds ?? $this->allowance($courier));
        $waitedMs = 0;

        for ($round = 0; $round < 5; $round++) {
            $waitMs = $this->attempt($courier, $lane);

            if ($waitMs === 0) {
                return 0;
            }

            if ($waitedMs + $waitMs > $allowanceMs) {
                return $this->seconds($waitMs);
            }

            Sleep::for($waitMs)->milliseconds();
            $waitedMs += $waitMs;
        }

        return max(1, $this->waitSeconds($courier, $lane));
    }

    /**
     * Seconds until $lane could take a call for the current caller, 0 when now.
     */
    public function waitSeconds(string $courier, string $lane): int
    {
        return $this->seconds($this->waitMs($courier, $lane, $this->calls($courier), $this->nowMs()));
    }

    /**
     * Seconds a caller of the current kind may wait for a slot inside one lookup.
     */
    public function allowance(string $courier): int
    {
        $pace = $this->settings($courier);

        return $this->context->isBulk() ? $pace['bulk_wait_seconds'] : $pace['live_wait_seconds'];
    }

    /**
     * The courier answered "too many requests" on $lane. Rests the lane, or
     * the whole courier once the limit is known to be shared. Returns the
     * rest in seconds.
     */
    public function rateLimited(string $courier, string $lane, int $retryAfterSeconds = 0): int
    {
        $pace = $this->settings($courier);
        $seconds = max($pace['rate_limit_rest_seconds'], min(self::MAX_REST_SECONDS, max(0, $retryAfterSeconds)));

        if (! $this->shared($courier) && $pace['per_window'] > 0) {
            $calls = $this->calls($courier);
            $since = $this->nowMs() - $pace['window_ms'];
            $laneCalls = count($this->times($calls, $lane, $since));
            $allCalls = count($this->times($calls, null, $since));

            if ($laneCalls <= $pace['per_window'] && $allCalls > $pace['per_window']) {
                try {
                    Cache::put($this->key($courier, 'shared'), true, now()->addDay());
                    LogHelper::saveLog(
                        'Courier pace',
                        ucfirst($courier).' limits all accounts together, so its calls are now counted for the whole courier.',
                        'courier-pace.log',
                    );
                } catch (Throwable) {
                    // The lane still rests below.
                }
            }
        }

        $this->rest($courier, $this->shared($courier) ? null : $lane, $seconds);

        return $seconds;
    }

    /**
     * True once one rate limit was seen to cover every account of the courier.
     */
    public function shared(string $courier): bool
    {
        try {
            return (bool) Cache::get($this->key($courier, 'shared'), false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Keep $lane (or the whole courier when null) free of calls for $seconds.
     * A rest is only ever extended, never cut short.
     */
    public function rest(string $courier, ?string $lane, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $key = $this->key($courier, 'rest', $lane ?? self::ALL);
        $untilMs = $this->nowMs() + $seconds * 1000;

        try {
            if ($untilMs > (int) Cache::get($key, 0)) {
                Cache::put($key, $untilMs, now()->addSeconds($seconds + 5));
            }
        } catch (Throwable $exception) {
            LogHelper::saveLog('Courier pace error', $courier.': '.CourierTransport::safeMessage($exception));
        }
    }

    /**
     * Seconds left on the rest of $lane or of the whole courier.
     */
    public function restingSeconds(string $courier, string $lane): int
    {
        $untilMs = max($this->restUntil($courier, self::ALL), $this->restUntil($courier, $lane));

        return $this->seconds(max(0, $untilMs - $this->nowMs()));
    }

    /**
     * A server error from a courier that blocks this way. After block
     * after_errors in a row the lane pauses, longer each time; a failed
     * probe pauses it again at once. Returns the pause in seconds, or 0
     * when the lane keeps going.
     */
    public function serverError(string $courier, string $lane): int
    {
        $block = $this->blockSettings($courier);

        if ($block === null) {
            return 0;
        }

        // Answers to calls sent before the pause began do not pause it again.
        $resting = $this->restingSeconds($courier, $lane);
        if ($resting > 0) {
            return $resting;
        }

        try {
            $wasProbe = Cache::pull($this->key($courier, 'probe', $lane)) !== null;
            $errorsKey = $this->key($courier, 'errors', $lane);
            Cache::add($errorsKey, 0, now()->addMinutes(10));
            $errors = (int) Cache::increment($errorsKey);

            if (! $wasProbe && $errors < $block['after_errors']) {
                return 0;
            }

            Cache::forget($errorsKey);

            $levelKey = $this->key($courier, 'level', $lane);
            $level = (int) Cache::get($levelKey, 0);
            $minutes = $block['pause_minutes'][min($level, count($block['pause_minutes']) - 1)];
            Cache::put($levelKey, $level + 1, now()->addHours(3));

            $seconds = $minutes * 60;
            $this->rest($courier, $lane, $seconds);
            Cache::put($this->key($courier, 'needs_probe', $lane), true, now()->addSeconds($seconds + 3 * 3600));
            $this->logPause($courier, $lane, $minutes, $wasProbe, $block['after_errors']);

            return $seconds;
        } catch (Throwable $exception) {
            LogHelper::saveLog('Courier pace error', $courier.': '.CourierTransport::safeMessage($exception));

            return 0;
        }
    }

    /**
     * A real answer on $lane. Clears the error count, and a probe that
     * answered ends the pause cycle.
     */
    public function answered(string $courier, string $lane): void
    {
        if ($this->blockSettings($courier) === null) {
            return;
        }

        try {
            Cache::forget($this->key($courier, 'errors', $lane));

            if (Cache::pull($this->key($courier, 'probe', $lane)) !== null) {
                Cache::forget($this->key($courier, 'needs_probe', $lane));
                Cache::forget($this->key($courier, 'level', $lane));
                LogHelper::saveLog('Courier pace', ucfirst($courier).' answered again after its pause.', 'courier-pace.log');
            }

            $runKey = $this->key($courier, 'run', $lane);
            $run = Cache::get($runKey);
            $run = is_array($run) ? $run : ['since' => $this->nowMs(), 'answers' => 0];
            $run['answers'] = (int) ($run['answers'] ?? 0) + 1;
            Cache::put($runKey, $run, now()->addHours(6));
        } catch (Throwable $exception) {
            LogHelper::saveLog('Courier pace error', $courier.': '.CourierTransport::safeMessage($exception));
        }
    }

    /**
     * Bulk calls per minute this courier can take over $lanes, and the
     * seconds until any lane is free when all of them rest.
     *
     * @param  list<string>  $lanes
     * @return array{per_minute: float, resting_seconds: int, shared: bool}
     */
    public function status(string $courier, array $lanes): array
    {
        $pace = $this->settings($courier);
        $lanes = array_values(array_unique($lanes));
        $shared = $this->shared($courier);
        $perLane = null;

        if ($pace['per_window'] > 0) {
            $allowed = max(1, $pace['per_window'] - $pace['live_reserve']);
            $perLane = $allowed * 60000 / $pace['window_ms'];
        }

        $gapMs = $this->bulkGapMs($courier);
        if ($gapMs > 0) {
            $perLane = min($perLane ?? PHP_FLOAT_MAX, 60000 / $gapMs);
        }

        $perLane ??= 60.0;
        $perMinute = $shared ? $perLane : $perLane * max(1, count($lanes));

        $resting = array_map(
            fn (string $lane): int => $this->restingSeconds($courier, $lane),
            $lanes === [] ? [self::ALL] : $lanes,
        );

        return [
            'per_minute' => round($perMinute, 1),
            'resting_seconds' => min($resting),
            'shared' => $shared,
        ];
    }

    private function attempt(string $courier, string $lane): int
    {
        try {
            return Cache::lock($this->key($courier, 'lock'), 5)->block(3, function () use ($courier, $lane): int {
                $nowMs = $this->nowMs();
                $calls = $this->calls($courier);
                $waitMs = $this->waitMs($courier, $lane, $calls, $nowMs);

                if ($waitMs > 0) {
                    return $waitMs;
                }

                if ($this->needsProbe($courier, $lane)) {
                    Cache::put($this->key($courier, 'probe', $lane), $nowMs, now()->addSeconds(self::PROBE_SECONDS));
                }

                $calls[] = [$lane, $nowMs];
                Cache::put($this->key($courier, 'calls'), $calls, now()->addSeconds($this->keepSeconds($courier)));

                return 0;
            });
        } catch (LockTimeoutException) {
            return $this->context->isBulk() ? 2000 : 0;
        } catch (Throwable $exception) {
            LogHelper::saveLog('Courier pace error', $courier.': '.CourierTransport::safeMessage($exception));

            return $this->context->isBulk() ? 2000 : 0;
        }
    }

    /**
     * @param  list<array{0: string, 1: int}>  $calls
     */
    private function waitMs(string $courier, string $lane, array $calls, int $nowMs): int
    {
        $pace = $this->settings($courier);
        $bulk = $this->context->isBulk();
        $scope = $this->shared($courier) ? null : $lane;

        $waitMs = max($this->restUntil($courier, self::ALL), $this->restUntil($courier, $lane)) - $nowMs;

        if ($pace['per_window'] > 0) {
            $allowed = $bulk ? max(1, $pace['per_window'] - $pace['live_reserve']) : $pace['per_window'];
            $times = $this->times($calls, $scope, $nowMs - $pace['window_ms']);
            $over = count($times) - $allowed;

            if ($over >= 0) {
                $waitMs = max($waitMs, $times[$over] + $pace['window_ms'] - $nowMs + 1);
            }
        }

        if ($bulk) {
            $gapMs = $this->bulkGapMs($courier);
            $recent = $gapMs > 0 ? $this->times($calls, $scope, $nowMs - $gapMs) : [];

            if ($recent !== []) {
                $waitMs = max($waitMs, (int) end($recent) + $gapMs - $nowMs);
            }
        }

        if ($waitMs <= 0 && $this->needsProbe($courier, $lane) && $this->probeInFlight($courier, $lane)) {
            $waitMs = self::PROBE_RECHECK_MS;
        }

        return max(0, $waitMs);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function calls(string $courier): array
    {
        try {
            $calls = Cache::get($this->key($courier, 'calls'));
        } catch (Throwable) {
            return [];
        }

        if (! is_array($calls)) {
            return [];
        }

        $cutoff = $this->nowMs() - $this->keepSeconds($courier) * 1000;
        $kept = [];

        foreach ($calls as $call) {
            if (is_array($call) && count($call) === 2 && (int) $call[1] > $cutoff) {
                $kept[] = [(string) $call[0], (int) $call[1]];
            }
        }

        return $kept;
    }

    /**
     * Call times after $sinceMs, oldest first, for one lane or all of them.
     *
     * @param  list<array{0: string, 1: int}>  $calls
     * @return list<int>
     */
    private function times(array $calls, ?string $lane, int $sinceMs): array
    {
        $times = [];

        foreach ($calls as [$callLane, $at]) {
            if ($at > $sinceMs && ($lane === null || $callLane === $lane)) {
                $times[] = $at;
            }
        }

        sort($times);

        return $times;
    }

    private function restUntil(string $courier, string $lane): int
    {
        try {
            return (int) Cache::get($this->key($courier, 'rest', $lane), 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function needsProbe(string $courier, string $lane): bool
    {
        try {
            return $this->blockSettings($courier) !== null && Cache::has($this->key($courier, 'needs_probe', $lane));
        } catch (Throwable) {
            return false;
        }
    }

    private function probeInFlight(string $courier, string $lane): bool
    {
        try {
            return Cache::has($this->key($courier, 'probe', $lane));
        } catch (Throwable) {
            return false;
        }
    }

    private function logPause(string $courier, string $lane, int $minutes, bool $wasProbe, int $afterErrors): void
    {
        $run = Cache::pull($this->key($courier, 'run', $lane));
        $answers = is_array($run) ? (int) ($run['answers'] ?? 0) : 0;
        $spanMinutes = is_array($run) ? max(0, (int) round(($this->nowMs() - (int) ($run['since'] ?? 0)) / 60000)) : 0;
        $name = ucfirst($courier);

        LogHelper::saveLog('Courier paused', $wasProbe
            ? "{$name} still failed after its pause, so it rests {$minutes} more minutes."
            : "{$name} failed {$afterErrors} times in a row with a server error after answering {$answers} lookups in about {$spanMinutes} minutes. It rests {$minutes} minutes, then one lookup checks whether it is back.",
            'courier-pace.log',
        );
    }

    /**
     * @return array{per_window: int, window_ms: int, live_reserve: int, bulk_gap_seconds: mixed, live_wait_seconds: int, bulk_wait_seconds: int, rate_limit_rest_seconds: int}
     */
    private function settings(string $courier): array
    {
        $raw = config('fraud_check.pace.'.$courier);
        $raw = is_array($raw) ? $raw : [];
        $perWindow = max(0, (int) ($raw['per_window'] ?? 0));

        return [
            'per_window' => $perWindow,
            'window_ms' => 1000 * max(1, (int) ($raw['window_seconds'] ?? 60)),
            // Bulk always keeps at least one lookup a window.
            'live_reserve' => max(0, min((int) ($raw['live_reserve'] ?? 0), $perWindow - 1)),
            'bulk_gap_seconds' => $raw['bulk_gap_seconds'] ?? null,
            'live_wait_seconds' => max(0, (int) ($raw['live_wait_seconds'] ?? 0)),
            'bulk_wait_seconds' => max(0, (int) ($raw['bulk_wait_seconds'] ?? 0)),
            'rate_limit_rest_seconds' => max(1, (int) ($raw['rate_limit_rest_seconds'] ?? 65)),
        ];
    }

    /**
     * The gap between two bulk calls. Without a set gap, bulk calls are
     * spread evenly over the window.
     */
    private function bulkGapMs(string $courier): int
    {
        $pace = $this->settings($courier);

        if (is_numeric($pace['bulk_gap_seconds'])) {
            return (int) round(max(0, (float) $pace['bulk_gap_seconds']) * 1000);
        }

        if ($pace['per_window'] <= 0) {
            return 0;
        }

        return intdiv($pace['window_ms'], max(1, $pace['per_window'] - $pace['live_reserve']));
    }

    /**
     * @return array{after_errors: int, pause_minutes: list<int>}|null
     */
    private function blockSettings(string $courier): ?array
    {
        $raw = config('fraud_check.pace.'.$courier.'.block');

        if (! is_array($raw) || ! ($raw['enabled'] ?? true)) {
            return null;
        }

        $first = max(1, (int) ($raw['pause_minutes'] ?? 5));
        $longest = max($first, (int) ($raw['max_pause_minutes'] ?? 30));
        $steps = [];

        for ($minutes = $first; $minutes < $longest; $minutes *= 2) {
            $steps[] = $minutes;
        }

        $steps[] = $longest;

        return [
            'after_errors' => max(1, (int) ($raw['after_errors'] ?? 2)),
            'pause_minutes' => $steps,
        ];
    }

    private function keepSeconds(string $courier): int
    {
        $pace = $this->settings($courier);

        return (int) ceil(max($pace['window_ms'], $this->bulkGapMs($courier)) / 1000) + 60;
    }

    private function key(string $courier, string $part, ?string $lane = null): string
    {
        return 'courier_pace:'.$courier.':'.$part.($lane !== null ? ':'.$lane : '');
    }

    private function nowMs(): int
    {
        return (int) now()->getPreciseTimestamp(3);
    }

    private function seconds(int $ms): int
    {
        return $ms <= 0 ? 0 : (int) ceil($ms / 1000);
    }
}
