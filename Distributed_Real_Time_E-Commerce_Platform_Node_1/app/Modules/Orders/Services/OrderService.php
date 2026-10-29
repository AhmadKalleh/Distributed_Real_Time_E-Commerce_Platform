<?php

namespace App\Modules\Orders\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Prices always come from the database, never from the client.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function place(User $customer, array $items): Order
    {
        // Merge duplicate lines for the same product.
        $quantities = [];
        foreach ($items as $item) {
            $quantities[$item['product_id']] = ($quantities[$item['product_id']] ?? 0) + $item['quantity'];
        }

        $order = DB::transaction(function () use ($customer, $quantities) {
            // Lock the rows so two concurrent orders cannot oversell the same stock.
            $products = Product::whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $problems = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if (! $product || ! $product->is_active) {
                    $problems[] = ['product_id' => $productId, 'reason' => 'Product is not available.'];
                } elseif ($product->stock < $quantity) {
                    $problems[] = ['product_id' => $productId, 'reason' => "Only {$product->stock} left in stock.", 'available' => $product->stock];
                }
            }

            if ($problems !== []) {
                throw new ApiException(409, 'INSUFFICIENT_STOCK', 'Some items cannot be ordered.', ['items' => $problems]);
            }

            // Sum in integer cents to avoid floating point rounding errors.
            $totalCents = 0;
            foreach ($quantities as $productId => $quantity) {
                $totalCents += (int) round($products[$productId]->price * 100) * $quantity;
            }

            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'total_amount' => number_format($totalCents / 100, 2, '.', ''),
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

        OrderPlaced::dispatch($order);

        return $order;
    }

    public function changeStatus(Order $order, OrderStatus $next): Order
    {
        [$order, $previous] = DB::transaction(function () use ($order, $next) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $order->status;

            if (! $current->canTransitionTo($next)) {
                throw new ApiException(422, 'INVALID_STATUS_TRANSITION', "Cannot change order status from {$current->value} to {$next->value}.", [
                    'current_status' => $current->value,
                    'allowed' => array_map(fn (OrderStatus $s) => $s->value, $current->allowedTransitions()),
                ]);
            }

            if ($next === OrderStatus::Cancelled) {
                // Return the reserved stock.
                foreach ($order->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => $next]);

            return [$order, $current];
        });

        OrderStatusChanged::dispatch($order, $previous, $next);

        return $order;
    }
}
