<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A domain error that should reach the client as-is, in the unified error format.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }
}
