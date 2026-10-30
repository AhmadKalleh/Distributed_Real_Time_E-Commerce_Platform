<?php
namespace App\Modules\Orders\Services;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Models\Order;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Orders\Exceptions\InsufficientStockException;
use App\Modules\Orders\Exceptions\InvalidStatusTransitionException;
use App\Modules\Orders\Repositories\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function getPaginatedOrders(User $user, array $validated): LengthAwarePaginator
    {
        return $this->orderRepository->getPaginatedOrders($user, $validated);
    }

    public function place(User $customer, array $items): Order
    {
        // 1. دمج الكميات للمنتجات المتكررة (Business Logic)
        $quantities = [];
        foreach ($items as $item) {
            $quantities[$item['product_id']] = ($quantities[$item['product_id']] ?? 0) + $item['quantity'];
        }

        // 2. التحقق من المخزون والأسعار
        $products = Product::whereIn('id', array_keys($quantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $reason = '';
        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $productName = $product ? $product->name : "Product #{$productId}";
            if (! $product || ! $product->is_active) {
                $reason = "The product '{$productName}' is currently unavailable or inactive.";
                break;
            } elseif ($product->stock < $quantity) {
                $reason = "The product '{$productName}' has only {$product->stock} items in stock, but you requested {$quantity}.";
                break;
            }
        }

        if ($reason !== '') {
            throw new InsufficientStockException($reason);
        }

        // 3. حساب المجموع بالقروش لتجنب أخطاء الفاصلة العائمة
        $totalCents = 0;
        foreach ($quantities as $productId => $quantity) {
            $totalCents += (int) round($products[$productId]->price * 100) * $quantity;
        }

        $totalAmount = $totalCents / 100;

        // 4. تفويض حفظ الطلب للـ Repository
        $order = $this->orderRepository->createOrder($customer, $totalAmount, $quantities, $products);

        // 5. إطلاق الحدث بعد النجاح
        OrderPlaced::dispatch($order);

        return $order;
    }

    public function changeStatus(Order $order, OrderStatus $next): Order
    {
        $current = $order->status;

        // التحقق من صحة تغير الحالة (Business Logic)
        if (! $current->canTransitionTo($next)) {
            throw new InvalidStatusTransitionException(
                currentStatus: $current->value,
                allowed: array_map(
                    fn (OrderStatus $s) => $s->value,
                    $current->allowedTransitions()
                ),
                message: "Cannot change order status from {$current->value} to {$next->value}."
            );
        }

        // تفويض تحديث الحالة وإعادة المخزون للـ Repository
        $updatedOrder = $this->orderRepository->updateStatus($order, $next);

        // إطلاق الحدث
        OrderStatusChanged::dispatch($updatedOrder, $current, $next);

        return $updatedOrder;
    }
}
