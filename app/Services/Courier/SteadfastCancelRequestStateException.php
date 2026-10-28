<?php

namespace App\Services\Courier;

/**
 * SteadFast's own cancel-request state rules out the decision the merchant asked for.
 *
 * The message is safe to show. context() carries the state the portal or
 * Packzy reported, so the plugin can save it instead of guessing.
 */
class SteadfastCancelRequestStateException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, private array $context = [], private int $status = 409)
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function status(): int
    {
        return $this->status;
    }
}
