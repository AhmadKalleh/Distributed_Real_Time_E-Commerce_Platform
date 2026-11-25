<?php
namespace App\Modules\Orders\Infrastructure\Persistence;

use App\Modules\Orders\Application\Contracts\OrderRepositoryInterface;
use App\Modules\Orders\Application\DTOs\Orders\StatusTransitionResult;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Exceptions\OrderStatusConflictException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

final class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function getPaginatedOrders(User $user, array $validated): CursorPaginator
    {
        $perPage = $validated['per_page'] ?? 15;
        $query = Order::query();

        if (! $user->isResponsible()) {
            $query->where('user_id', $user->id);
        }

        return $query
        ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
        ->with('items.product')
        ->latest('id') // أو ->orderByDesc('id') لضمان الترتيب الصحيح للمؤشر
        ->cursorPaginate($perPage, ['*'], 'cursor') // استبدلنا paginate بـ cursorPaginate
        ->appends(collect($validated)->except('cursor')->all());
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

    public function transitionStatus(int $orderId, OrderStatus $next): StatusTransitionResult
    {
        return DB::transaction(function () use ($orderId, $next) {
            $order = Order::query()
                ->useWritePdo()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $order->status;

            if (! $from->canTransitionTo($next)) {
                throw new OrderStatusConflictException($from->value, $next->value);
            }

            $order->status = $next;
            $order->save();

            DB::table('order_status_outbox')->insert([
                'order_id' => $order->id,
                'event_type' => 'order.status_changed',
                'from_status' => $from->value,
                'to_status' => $next->value,
                'payload' => json_encode([
                    'order_id' => $order->id,
                    'from' => $from->value,
                    'to' => $next->value,
                ], JSON_THROW_ON_ERROR),
                'recorded_at' => now(),
            ]);

            return new StatusTransitionResult(
                $order->fresh(['items.product']),
                $from,
                $next,
            );
        });
    }
}
