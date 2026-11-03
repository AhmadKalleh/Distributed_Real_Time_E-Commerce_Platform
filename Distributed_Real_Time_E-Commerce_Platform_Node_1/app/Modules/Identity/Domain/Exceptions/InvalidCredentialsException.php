<?php

namespace App\Modules\Identity\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

class InvalidCredentialsException extends BaseApiException
{
    public function __construct(
        string $message = 'Invalid email or password.'
    ) {
        parent::__construct($message, 401);
    }
}
