<?php

namespace App\Modules\Orders\Application\UseCases;

use App\Modules\Orders\Application\Contracts\OrderServiceInterface;
use App\Modules\Orders\Application\DTOs\Orders\PlaceOrderDTO;
use App\Modules\Orders\Domain\Entities\Order;

final readonly class PlaceOrderUseCase
{
    public function __construct(private OrderServiceInterface $orders) {}

    public function execute(PlaceOrderDTO $dto): Order
    {
        return $this->orders->placeOrder($dto);
    }
}
