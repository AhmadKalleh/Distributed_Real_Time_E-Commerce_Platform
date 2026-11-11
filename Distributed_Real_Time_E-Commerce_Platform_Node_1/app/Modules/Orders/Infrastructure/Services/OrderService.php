<?php

namespace App\Modules\Orders\Infrastructure\Services;

use App\Modules\Catalog\Application\Contracts\ProductCatalogInterface;
use App\Modules\Catalog\Domain\Exceptions\ProductUnavailableException;
use App\Shared\Contracts\InventoryGatewayInterface;
use App\Modules\Orders\Application\Contracts\OrderRepositoryInterface;
use App\Modules\Orders\Application\Contracts\OrderServiceInterface;
use App\Modules\Orders\Application\DTOs\Orders\ChangeOrderStatusDTO;
use App\Modules\Orders\Application\DTOs\Orders\ListOrdersDTO;
use App\Modules\Orders\Application\DTOs\Orders\PlaceOrderDTO;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Events\OrderPlaced;
use App\Modules\Orders\Domain\Events\OrderStatusChanged;
use App\Modules\Orders\Domain\Exceptions\InsufficientStockException;
use App\Modules\Orders\Domain\Exceptions\InvalidStatusTransitionException;
use App\Modules\Orders\Domain\Exceptions\InventoryCompensationFailedException;
use App\Modules\Orders\Domain\Exceptions\InventoryServiceUnavailableException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Coordinates Orders use cases using module-owned ports and external module contracts. */
final readonly class OrderService implements OrderServiceInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InventoryGatewayInterface $inventory,
        private ProductCatalogInterface $catalog,
    ) {}

    public function listOrders(ListOrdersDTO $dto): CursorPaginator
    {
        return $this->orderRepository->getPaginatedOrders(
            $dto->customer,
            $dto->filters,
        );
    }



    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        $quantities = $this->mergeDuplicateItems($dto->items);



        $products = $this->getActiveProductsOrFail(
            array_keys($quantities)
        );

        $totalAmount = $this->calculateTotal($quantities, $products);

        /** @var array<int, string> $unitPricesByProductId */
        $unitPricesByProductId = $products
            ->map(fn ($product) => $product->price)
            ->all();

        $reservationReference = (string) Str::uuid();

        $this->reserveStock($reservationReference, $quantities);

        $order = $this->saveOrderOrCompensate(
            $dto,
            $totalAmount,
            $quantities,
            $unitPricesByProductId,
            $reservationReference,
        );

        // Publish only after the local database transaction has committed.
        OrderPlaced::dispatch($order);

        return $order;
    }

    public function changeOrderStatus(ChangeOrderStatusDTO $dto): Order
    {
        $order = $dto->order;
        $next = $dto->nextStatus;
        $current = $order->status;

        // A repeated Cancelled request safely retries a previously failed release.
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
                    $current->allowedTransitions(),
                ),
                message: "Cannot change order status from {$current->value} to {$next->value}.",
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


    private function getActiveProductsOrFail(array $productIds): Collection
    {
        try {
            return $this->catalog->getActiveProductsByIds($productIds);
        } catch (ProductUnavailableException $exception) {
            throw new InsufficientStockException($exception->getMessage());
        }
    }

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


    private function calculateTotal(array $quantities, Collection $products): float
    {
        $totalCents = 0;

        foreach ($quantities as $productId => $quantity) {
            $unitPriceCents = (int) round(((float) $products->get($productId)->price) * 100);
            $totalCents += $unitPriceCents * $quantity;
        }

        return $totalCents / 100;
    }


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


    private function releaseAfterUnknownReserveOutcome(
        string $reservationReference,
        Throwable $reserveException,
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


    private function saveOrderOrCompensate(
        PlaceOrderDTO $dto,
        float|int $totalAmount,
        array $quantities,
        array $unitPricesByProductId,
        string $reservationReference,
    ): Order {
        try {
            $this->failIfOrderSaveFailureIsForced();

            return $this->orderRepository->createOrder(
                $dto->customer,
                $totalAmount,
                $quantities,
                $unitPricesByProductId,
                $reservationReference,
            );
        } catch (Throwable $saveException) {
            $this->releaseAfterSaveFailure($reservationReference, $saveException);

            throw $saveException;
        }
    }

    private function releaseAfterSaveFailure(
        string $reservationReference,
        Throwable $saveException,
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


    private function failIfOrderSaveFailureIsForced(): void
    {
        if (app()->environment(['local', 'testing']) &&
            (bool) config('services.inventory.force_order_save_failure', false)) {
            throw new RuntimeException('Intentional test failure after stock reservation.');
        }
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
