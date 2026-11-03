<?php

namespace App\Modules\Catalog\Application\DTOs\Products;

final readonly class ListProductsDTO
{
    public function __construct(
        public array $filters,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            filters: $data,
        );
    }
}
