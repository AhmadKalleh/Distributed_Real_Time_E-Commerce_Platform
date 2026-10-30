<?php
namespace App\Modules\Orders\Repositories;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Catalog\Domain\Entities\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
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

    public function createOrder(User $customer, float $totalAmount, array $quantities, Collection $products): Order
    {
        return DB::transaction(function () use ($customer, $totalAmount, $quantities, $products) {
            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
            ]);

            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];
                $product->decrement('stock', $quantity);

                $order->items()->create([
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
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

            if ($next === OrderStatus::Cancelled) {
                foreach ($lockedOrder->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $lockedOrder->update(['status' => $next]);

            return $lockedOrder;
        });
    }
}
