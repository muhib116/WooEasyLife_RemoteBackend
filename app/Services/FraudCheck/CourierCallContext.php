<?php

namespace App\Services\FraudCheck;

/**
 * Who is asking the couriers in this process. A merchant's live order check
 * is served first; a bulk run, its waiting lane, and background snapshot
 * refreshes use what is left of a courier's pace.
 */
class CourierCallContext
{
    public const LIVE = 'live';

    public const BULK = 'bulk';

    private string $mode = self::LIVE;

    public function mode(): string
    {
        return $this->mode;
    }

    public function isBulk(): bool
    {
        return $this->mode === self::BULK;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function asBulk(callable $callback): mixed
    {
        $previous = $this->mode;
        $this->mode = self::BULK;

        try {
            return $callback();
        } finally {
            $this->mode = $previous;
        }
    }
}
