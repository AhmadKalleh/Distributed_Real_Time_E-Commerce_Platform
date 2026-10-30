<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class ProductSlugAlreadyExistsException extends BaseApiException
{
    public function __construct(string $message = 'A product with this name already exists.')
    {
        parent::__construct($message, 409);
    }
}
