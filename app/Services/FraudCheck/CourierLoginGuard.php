<?php

namespace App\Services\FraudCheck;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * One login per courier account at a time. A worker that waits uses the
 * token the first one saved instead of logging in again. A failed login
 * rests that account, so a wrong password or a courier lockout is not tried
 * again on every number; the next saved account is still tried. A changed
 * password is a new account here, so a fix by the admin is used at once.
 */
class CourierLoginGuard
{
    public const REJECTED_REST_SECONDS = 600;

    public const LIMITED_REST_SECONDS = 300;

    public const FAILED_REST_SECONDS = 30;

    private const LOCK_SECONDS = 90;

    private const WAIT_SECONDS = 20;

    /**
     * @template T
     *
     * @param  array{identifier: string, password: string}  $account
     * @param  callable(): (T|null)  $saved  The token another worker may have saved meanwhile.
     * @param  callable(): (T|null)  $login  Logs in and saves the token. Calls restAfter() when it fails.
     * @return T|null
     */
    public function login(string $courier, array $account, callable $saved, callable $login): mixed
    {
        if ($this->resting($courier, $account)) {
            return null;
        }

        try {
            return Cache::lock($this->key('lock', $courier, $account), self::LOCK_SECONDS)
                ->block(self::WAIT_SECONDS, function () use ($courier, $account, $saved, $login) {
                    $token = $saved();

                    if ($token !== null) {
                        return $token;
                    }

                    if ($this->resting($courier, $account)) {
                        return null;
                    }

                    try {
                        $token = $login();
                    } catch (Throwable $exception) {
                        $this->rest($courier, $account, self::FAILED_REST_SECONDS);

                        throw $exception;
                    }

                    if ($token !== null) {
                        Cache::forget($this->key('rest', $courier, $account));
                    }

                    return $token;
                });
        } catch (LockTimeoutException) {
            return $saved();
        }
    }

    /**
     * A rejected password rests longest, a courier rate limit next, an
     * outage briefly. A success status with no token is a rejection.
     *
     * @param  array{identifier: string, password: string}  $account
     */
    public function restAfter(string $courier, array $account, ?int $status): void
    {
        $seconds = match (true) {
            $status === 429 => self::LIMITED_REST_SECONDS,
            $status === null, $status >= 500, $status === 408 => self::FAILED_REST_SECONDS,
            default => self::REJECTED_REST_SECONDS,
        };

        $this->rest($courier, $account, $seconds);
    }

    /**
     * @param  array{identifier: string, password: string}  $account
     */
    public function rest(string $courier, array $account, int $seconds): void
    {
        if ($seconds > 0) {
            Cache::put($this->key('rest', $courier, $account), time() + $seconds, $seconds);
        }
    }

    /**
     * @param  array{identifier: string, password: string}  $account
     */
    public function resting(string $courier, array $account): bool
    {
        return Cache::has($this->key('rest', $courier, $account));
    }

    /**
     * Drop a token the courier rejected, unless another worker already put a
     * fresh one in its place.
     */
    public function forgetToken(string $cacheKey, mixed $rejected): void
    {
        if (Cache::get($cacheKey) == $rejected) {
            Cache::forget($cacheKey);
        }
    }

    /**
     * @param  array{identifier: string, password: string}  $account
     */
    private function key(string $kind, string $courier, array $account): string
    {
        $identifier = strtolower(trim((string) ($account['identifier'] ?? '')));

        return 'fraud_check_login_'.$kind.'_'.$courier.'_'.md5($identifier.'|'.(string) ($account['password'] ?? ''));
    }
}
