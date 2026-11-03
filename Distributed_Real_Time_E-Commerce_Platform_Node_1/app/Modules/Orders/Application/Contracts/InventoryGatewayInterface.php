<?php

namespace App\Modules\Orders\Application\Contracts;

/**
 * Application port for the external Inventory service.
 * The gRPC protocol and generated messages stay behind its implementation.
 */
interface InventoryGatewayInterface
{
    public function getAvailableQuantity(int $productId): ?int;

    /** @param array<int|string, int> $quantities keyed by product ID */
    public function reserveStock(string $reservationReference, array $quantities): void;

    public function releaseStock(string $reservationReference): void;
}
