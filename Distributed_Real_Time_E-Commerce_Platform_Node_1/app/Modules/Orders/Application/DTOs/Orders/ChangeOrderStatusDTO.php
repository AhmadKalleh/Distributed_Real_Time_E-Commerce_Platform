<?php

namespace App\Modules\Orders\Application\DTOs\Orders;

use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Enums\OrderStatus;

final readonly class ChangeOrderStatusDTO
{
    public function __construct(
        public Order $order,
        public OrderStatus $nextStatus,
    ) {}
}
