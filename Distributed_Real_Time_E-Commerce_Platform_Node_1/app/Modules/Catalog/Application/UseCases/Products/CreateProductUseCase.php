<?php

namespace App\Modules\Catalog\Application\UseCases\Products;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\DTOs\Products\CreateProductDTO;
use App\Modules\Catalog\Domain\Entities\Product;

final readonly class CreateProductUseCase
{
    public function __construct(private CatalogServiceInterface $catalog) {}

    public function execute(CreateProductDTO $dto): Product
    {
        return $this->catalog->createProduct($dto);
    }
}
