<?php

namespace App\Modules\Orders\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class InvalidStatusTransitionException extends BaseApiException
{
    
    public function __construct(
        public readonly string $currentStatus,
        public readonly array $allowed,
        string $message
    ) {
        parent::__construct($message, 422);
    }
}
