<?php

namespace App\Shared\Exceptions;

use RuntimeException;

abstract class BaseApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 400
    ) {
        parent::__construct($message);
    }
}
