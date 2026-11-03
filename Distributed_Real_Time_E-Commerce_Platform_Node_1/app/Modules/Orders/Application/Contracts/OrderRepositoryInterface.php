<?php

namespace App\Modules\Orders\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function getPaginatedOrders(User $user, array $validated): LengthAwarePaginator;

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

    public function updateStatus(Order $order, OrderStatus $next): Order;

    public function lockAndFind(int $orderId): Order;
}
