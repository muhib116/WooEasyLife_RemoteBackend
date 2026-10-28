<?php

namespace App\Services\ReturnAi;

class ReturnAiException extends \RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 502)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}
