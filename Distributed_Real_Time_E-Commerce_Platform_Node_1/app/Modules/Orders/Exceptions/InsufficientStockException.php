<?php

namespace App\Modules\Orders\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class InsufficientStockException extends BaseApiException
{

    public function __construct(
        string $message = 'Some items cannot be ordered.'
    ) {
        parent::__construct($message, 409);
    }
}
