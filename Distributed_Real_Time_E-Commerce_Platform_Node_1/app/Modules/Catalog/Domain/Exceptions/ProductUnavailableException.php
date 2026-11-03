<?php

namespace App\Modules\Catalog\Domain\Exceptions;
use App\Shared\Exceptions\BaseApiException;

use RuntimeException;

/** Raised when a requested product is missing or cannot currently be sold. */
final class ProductUnavailableException extends BaseApiException
{
    public function __construct(string $productname)
    {
        $message = "The product '{$productname}' is currently unavailable or inactive.";
        parent::__construct($message, 404);
    }
}
