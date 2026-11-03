<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class ProductImageStorageException extends BaseApiException
{
    public function __construct(string $message = 'Unable to store product image.')
    {
        parent::__construct($message, 500);
    }
}
