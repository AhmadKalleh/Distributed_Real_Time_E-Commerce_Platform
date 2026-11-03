<?php

namespace App\Modules\Orders\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class InventoryCompensationFailedException extends BaseApiException
{
    public function __construct(public readonly string $reservationReference)
    {
        parent::__construct(
            'The order could not be saved and the inventory release could not be confirmed. Contact support with reservation reference: '.$reservationReference,
            503
        );
    }
}
