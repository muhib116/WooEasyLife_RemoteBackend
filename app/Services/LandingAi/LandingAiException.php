<?php

namespace App\Services\LandingAi;

/**
 * A generation problem with a message that is safe to show to the merchant.
 */
class LandingAiException extends \RuntimeException
{
    public function __construct(string $message, private int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
