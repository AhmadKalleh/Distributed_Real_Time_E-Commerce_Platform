<?php

namespace App\Modules\Orders\Application\UseCases;

use App\Modules\Orders\Application\Contracts\OrderServiceInterface;
use App\Modules\Orders\Application\DTOs\Orders\ChangeOrderStatusDTO;
use App\Modules\Orders\Domain\Entities\Order;

final readonly class ChangeOrderStatusUseCase
{
    public function __construct(private OrderServiceInterface $orders) {}

    public function execute(ChangeOrderStatusDTO $dto): Order
    {
        return $this->orders->changeOrderStatus($dto);
    }
}
