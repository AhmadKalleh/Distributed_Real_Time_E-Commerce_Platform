<?php

namespace App\Modules\Catalog\Application\DTOs\Products;

/**
 * Read-only product data exposed by Catalog to other modules.
 * It deliberately contains no Eloquent model or relationship.
 */
final readonly class ProductSnapshot
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
    ) {}
}
