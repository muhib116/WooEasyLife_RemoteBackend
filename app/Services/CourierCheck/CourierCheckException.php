<?php

namespace App\Services\CourierCheck;

use RuntimeException;

class CourierCheckException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly int $status,
    ) {
        parent::__construct($error);
    }
}
