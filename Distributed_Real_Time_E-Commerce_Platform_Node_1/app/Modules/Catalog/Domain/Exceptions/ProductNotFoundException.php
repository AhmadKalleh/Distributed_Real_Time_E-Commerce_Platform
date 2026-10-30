<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class ProductNotFoundException extends BaseApiException
{
    public function __construct(string $message = 'Product not found.')
    {
        parent::__construct($message, 404);
    }
}
