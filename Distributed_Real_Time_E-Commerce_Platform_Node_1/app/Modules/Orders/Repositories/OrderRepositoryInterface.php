<?php
namespace App\Modules\Orders\Repositories;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface OrderRepositoryInterface
{
    public function getPaginatedOrders(User $user, array $validated): LengthAwarePaginator;

    public function createOrder(User $customer, float $totalAmount, array $quantities, Collection $products): Order;

    public function updateStatus(Order $order, OrderStatus $next): Order;

    public function lockAndFind(int $orderId): Order;
}
