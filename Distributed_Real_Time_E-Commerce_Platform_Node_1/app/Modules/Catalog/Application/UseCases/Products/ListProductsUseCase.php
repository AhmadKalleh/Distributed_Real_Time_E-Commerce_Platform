<?php

namespace App\Modules\Catalog\Application\UseCases\Products;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListProductsUseCase
{
    public function __construct(
        private CatalogServiceInterface $catalog
    ) {}

    public function execute(
        ListProductsDTO $dto
    ): LengthAwarePaginator {
        return $this->catalog->listProducts($dto);
    }
}
