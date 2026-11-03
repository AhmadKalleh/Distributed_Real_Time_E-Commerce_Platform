<?php

namespace App\Modules\Orders\Services;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Orders\Exceptions\InsufficientStockException;
use App\Modules\Orders\Exceptions\InvalidStatusTransitionException;
use App\Modules\Orders\Exceptions\InventoryCompensationFailedException;
use App\Modules\Orders\Exceptions\InventoryServiceUnavailableException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Repositories\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected InventoryGrpcClient $inventory,
    ) {}

    public function getPaginatedOrders(User $user, array $validated): LengthAwarePaginator
    {
        return $this->orderRepository->getPaginatedOrders($user, $validated);
    }

    public function place(User $customer, array $items): Order
    {
        $quantities = $this->mergeDuplicateItems($items);

        $products = $this->loadActiveProducts($quantities);

        $totalAmount = $this->calculateTotal($quantities, $products);

        $reservationReference = (string) Str::uuid();
        $this->reserveStock($reservationReference, $quantities);

        $order = $this->saveOrderOrCompensate(
            $customer,
            $totalAmount,
            $quantities,
            $products,
            $reservationReference
        );

        // Dispatch only after the local transaction committed successfully.
        OrderPlaced::dispatch($order);

        return $order;
    }

    /**
     * Merge duplicate product IDs before the single gRPC reservation call.
     *
     * @return array<int, int> [productId => totalQuantity]
     */
    private function mergeDuplicateItems(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantities[$productId] = ($quantities[$productId] ?? 0) + (int) $item['quantity'];
        }

        ksort($quantities, SORT_NUMERIC);

        return $quantities;
    }

    /**
     * Read product names/prices/active state from Catalog only.
     * Stock is NOT read from or changed in the Orders database anymore.
     *
     * @param  array<int, int>  $quantities
     */
    private function loadActiveProducts(array $quantities): Collection
    {
        $products = Product::whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        foreach (array_keys($quantities) as $productId) {
            $product = $products->get($productId);

            if (! $product || ! $product->is_active) {
                $productName = $product?->name ?? "Product #{$productId}";

                throw new InsufficientStockException(
                    "The product '{$productName}' is currently unavailable or inactive."
                );
            }
        }

        return $products;
    }

    /**
     * Calculate money in cents to avoid floating-point accumulation errors.
     *
     * @param  array<int, int>  $quantities
     */
    private function calculateTotal(array $quantities, Collection $products): float|int
    {
        $totalCents = 0;

        foreach ($quantities as $productId => $quantity) {
            $unitPriceCents = (int) round(((float) $products[$productId]->price) * 100);
            $totalCents += $unitPriceCents * $quantity;
        }

        return $totalCents / 100;
    }

    /**
     * Reserve ALL requested product quantities in one remote operation.
     * The unique reference is also the idempotency key for retries and release.
     *
     * @param  array<int, int>  $quantities
     */
    private function reserveStock(string $reservationReference, array $quantities): void
    {
        try {
            $this->inventory->reserveStock($reservationReference, $quantities);
        } catch (InsufficientStockException $exception) {
            throw $exception;
        } catch (InventoryServiceUnavailableException $exception) {
            $this->releaseAfterUnknownReserveOutcome($reservationReference, $exception);

            throw $exception;
        }
    }

    /**
     * A timeout does not prove that the remote transaction did not commit.
     * ReleaseStock writes an idempotent tombstone when no reservation exists,
     * preventing a delayed ReserveStock with this reference from succeeding.
     */
    private function releaseAfterUnknownReserveOutcome(
        string $reservationReference,
        Throwable $reserveException
    ): void {
        try {
            $this->inventory->releaseStock($reservationReference);
        } catch (Throwable $releaseException) {
            Log::critical('Inventory reservation outcome is unknown and timeout compensation failed.', [
                'reservation_reference' => $reservationReference,
                'reserve_error' => $reserveException->getMessage(),
                'release_error' => $releaseException->getMessage(),
            ]);
        }
    }

    /**
     * Save Order + OrderItems in one local database transaction.
     * If that fails, compensate by releasing the remote reservation.
     *
     * @param  array<int, int>  $quantities
     */
    private function saveOrderOrCompensate(
        User $customer,
        float|int $totalAmount,
        array $quantities,
        Collection $products,
        string $reservationReference
    ): Order {
        try {
            $this->failIfOrderSaveFailureIsForced();

            return $this->orderRepository->createOrder(
                $customer,
                $totalAmount,
                $quantities,
                $products,
                $reservationReference
            );
        } catch (Throwable $saveException) {
            $this->releaseAfterSaveFailure($reservationReference, $saveException);

            throw $saveException;
        }
    }

    /**
     * Release the remote reservation after a local save failure.
     * Throws InventoryCompensationFailedException if the release cannot be confirmed.
     */
    private function releaseAfterSaveFailure(
        string $reservationReference,
        Throwable $saveException
    ): void {
        try {
            $this->inventory->releaseStock($reservationReference);
        } catch (Throwable $releaseException) {
            Log::critical('Order save failed and inventory compensation could not be confirmed.', [
                'reservation_reference' => $reservationReference,
                'save_error' => $saveException->getMessage(),
                'release_error' => $releaseException->getMessage(),
            ]);

            throw new InventoryCompensationFailedException($reservationReference);
        }
    }

    /**
     * Controlled failure injection for local/testing environments only.
     * Enable with INVENTORY_TEST_FAIL_AFTER_RESERVE=true to prove compensation.
     */
    private function failIfOrderSaveFailureIsForced(): void
    {
        if (app()->environment(['local', 'testing']) &&
            (bool) config('services.inventory.force_order_save_failure', false)) {
            throw new RuntimeException('Intentional test failure after stock reservation.');
        }
    }

    public function changeStatus(Order $order, OrderStatus $next): Order
    {
        $current = $order->status;

        // A repeated Cancelled request safely retries a previously failed release.
        // ReleaseStock is idempotent, so this cannot put the same quantity back twice.
        if ($current === OrderStatus::Cancelled &&
            $next === OrderStatus::Cancelled &&
            $order->inventory_reservation_reference) {
            $this->releaseCancelledOrderReservation($order);
            return $order->fresh(['items.product']);
        }

        if (! $current->canTransitionTo($next)) {
            throw new InvalidStatusTransitionException(
                currentStatus: $current->value,
                allowed: array_map(
                    fn (OrderStatus $status) => $status->value,
                    $current->allowedTransitions()
                ),
                message: "Cannot change order status from {$current->value} to {$next->value}."
            );
        }

        $updatedOrder = $this->orderRepository->updateStatus($order, $next);
        OrderStatusChanged::dispatch($updatedOrder, $current, $next);

        // Stock restoration is owned by Inventory, never by the Orders database.
        if ($next === OrderStatus::Cancelled && $updatedOrder->inventory_reservation_reference) {
            $this->releaseCancelledOrderReservation($updatedOrder);
        }

        return $updatedOrder;
    }

    private function releaseCancelledOrderReservation(Order $order): void
    {
        try {
            $this->inventory->releaseStock($order->inventory_reservation_reference);
        } catch (Throwable $exception) {
            Log::critical('Order is cancelled but its inventory reservation release is still pending.', [
                'order_id' => $order->id,
                'reservation_reference' => $order->inventory_reservation_reference,
                'error' => $exception->getMessage(),
            ]);

            throw new InventoryServiceUnavailableException(
                'The order is now Cancelled, but returning its quantity to inventory is not yet confirmed. ' .
                'Retry the same cancellation request safely; the inventory release is idempotent.'
            );
        }
    }
}
