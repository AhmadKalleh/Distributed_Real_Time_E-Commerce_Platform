<?php

namespace App\Modules\Orders\Application\Contracts;

use App\Modules\Orders\Application\DTOs\Orders\ChangeOrderStatusDTO;
use App\Modules\Orders\Application\DTOs\Orders\ListOrdersDTO;
use App\Modules\Orders\Application\DTOs\Orders\PlaceOrderDTO;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    public function listOrders(ListOrdersDTO $dto): LengthAwarePaginator;

    public function placeOrder(PlaceOrderDTO $dto): Order;

    public function changeOrderStatus(ChangeOrderStatusDTO $dto): Order;
}
