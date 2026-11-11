<?php

namespace App\Modules\Orders\Application\UseCases;

use App\Modules\Orders\Application\Contracts\OrderServiceInterface;
use App\Modules\Orders\Application\DTOs\Orders\ListOrdersDTO;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class ListOrdersUseCase
{
    public function __construct(private OrderServiceInterface $orders) {}

    public function execute(ListOrdersDTO $dto): CursorPaginator
    {
        return $this->orders->listOrders($dto);
    }
}
