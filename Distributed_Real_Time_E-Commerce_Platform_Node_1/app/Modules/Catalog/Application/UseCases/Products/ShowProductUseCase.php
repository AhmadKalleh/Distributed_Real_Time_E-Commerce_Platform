<?php

namespace App\Modules\Catalog\Application\UseCases\Products;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\DTOs\Products\ShowProductDTO;
use App\Modules\Catalog\Domain\Entities\Product;

final readonly class ShowProductUseCase
{
    public function __construct(
        private CatalogServiceInterface $catalog
    ) {}

    public function execute(
        ShowProductDTO $dto
    ): Product {
        return $this->catalog->showProduct($dto);
    }
}
