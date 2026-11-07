<?php

namespace App\Shared\Contracts;

/**
 * Application port for the external Inventory service.
 * The protocol and generated messages stay behind its implementation.
 */
interface InventoryGatewayInterface
{
    public function getAvailableQuantity(int $productId): ?int;

    public function initializeProductStock(int $productId, int $quantity): void;

    /** @param array<int|string, int> $quantities keyed by product ID */
    public function reserveStock(string $reservationReference, array $quantities): void;

    public function releaseStock(string $reservationReference): void;
}
