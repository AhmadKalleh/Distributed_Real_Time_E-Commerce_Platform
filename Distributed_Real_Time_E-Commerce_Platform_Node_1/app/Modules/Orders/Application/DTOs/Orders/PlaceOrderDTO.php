<?php

namespace App\Modules\Orders\Application\DTOs\Orders;

use App\Modules\Identity\Domain\Entities\User;

final readonly class PlaceOrderDTO
{
    /**
     * @param list<array{product_id: int|string, quantity: int|string}> $items
     */
    public function __construct(
        public User $customer,
        public array $items,
    ) {}
}
