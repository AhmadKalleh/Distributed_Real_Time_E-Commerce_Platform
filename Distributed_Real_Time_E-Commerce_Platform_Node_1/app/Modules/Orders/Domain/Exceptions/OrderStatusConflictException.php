<?php

namespace App\Modules\Orders\Domain\Exceptions;

use App\Shared\Exceptions\BaseApiException;

final class OrderStatusConflictException extends BaseApiException
{
    public function __construct(
        public readonly string $currentStatus,
        public readonly string $requestedStatus,
    ) {
        parent::__construct(
            "Cannot change order status from {$currentStatus} to {$requestedStatus}.",
            409,
        );
    }
}
