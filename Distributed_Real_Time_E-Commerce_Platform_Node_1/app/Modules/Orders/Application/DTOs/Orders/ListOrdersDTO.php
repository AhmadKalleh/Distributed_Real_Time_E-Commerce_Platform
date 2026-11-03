<?php

namespace App\Modules\Orders\Application\DTOs\Orders;

use App\Modules\Identity\Domain\Entities\User;

final readonly class ListOrdersDTO
{
    /** @param array{status?: string|null, per_page?: int|null} $filters */
    public function __construct(
        public User $customer,
        public array $filters,
    ) {}
}
