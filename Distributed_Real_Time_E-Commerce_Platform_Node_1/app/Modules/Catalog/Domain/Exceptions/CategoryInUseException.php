<?php

namespace App\Modules\Catalog\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class CategoryInUseException extends BaseApiException
{
    public function __construct(string $message = 'The category cannot be used for this operation.')
    {
        parent::__construct($message, 409);
    }
}
