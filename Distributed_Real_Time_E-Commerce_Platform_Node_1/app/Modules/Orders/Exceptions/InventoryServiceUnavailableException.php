<?php

namespace App\Modules\Orders\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class InventoryServiceUnavailableException extends BaseApiException
{
    public function __construct(
        string $message = 'The inventory service is temporarily unavailable. Please try again.'
    ) {
        parent::__construct($message, 503);
    }
}
