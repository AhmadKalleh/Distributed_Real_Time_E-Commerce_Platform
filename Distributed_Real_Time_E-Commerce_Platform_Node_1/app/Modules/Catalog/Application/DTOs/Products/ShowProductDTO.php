<?php

namespace App\Modules\Catalog\Application\DTOs\Products;

final readonly class ShowProductDTO
{
    public function __construct(
        public int $productId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id']
        );
    }
}
