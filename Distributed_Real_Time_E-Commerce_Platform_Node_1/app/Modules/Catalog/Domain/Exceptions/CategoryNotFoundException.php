<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class CategoryNotFoundException extends BaseApiException
{
    public function __construct(string $message = 'Category not found.')
    {
        parent::__construct($message, 404);
    }
}
