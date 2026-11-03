<?php
namespace App\Modules\Orders\Infrastructure\Persistence;

use App\Modules\Orders\Application\Contracts\OrderRepositoryInterface;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function getPaginatedOrders(User $user, array $validated): LengthAwarePaginator
    {
        $perPage = $validated['per_page'] ?? 15;
        $query = Order::query();

        if (! $user->isResponsible()) {
            $query->where('user_id', $user->id);
        }

        return $query
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with('items.product')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

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
    ): Order {
        return DB::transaction(function () use (
            $customer,
            $totalAmount,
            $quantities,
            $unitPricesByProductId,
            $reservationReference
        ) {
            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'inventory_reservation_reference' => $reservationReference,
            ]);

            foreach ($quantities as $productId => $quantity) {
                $unitPrice = $unitPricesByProductId[$productId];

                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);
            }

            return $order;
        });
    }

    public function lockAndFind(int $orderId): Order
    {
        return Order::whereKey($orderId)->lockForUpdate()->firstOrFail();
    }

    public function updateStatus(Order $order, OrderStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedOrder->update(['status' => $next]);

            return $lockedOrder->fresh(['items.product']);
        });
    }
}
