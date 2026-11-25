<?php

namespace App\Modules\Orders\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Application\DTOs\Orders\StatusTransitionResult;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface OrderRepositoryInterface
{
    public function getPaginatedOrders(User $user, array $validated): CursorPaginator;

    /**
     * @param array<int, int> $quantities
     * @param array<int, string> $unitPricesByProductId
     */
    public function createOrder(
        User $customer,
        float $totalAmount,
        array $quantities,
        array $unitPricesByProductId,
        string $reservationReference
    ): Order;

    public function transitionStatus(int $orderId, OrderStatus $next): StatusTransitionResult;
}
