<?php

namespace App\Exceptions;

use InvalidArgumentException;

class DownloadGateFieldException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $field,
    ) {
        parent::__construct($message);
    }
}
