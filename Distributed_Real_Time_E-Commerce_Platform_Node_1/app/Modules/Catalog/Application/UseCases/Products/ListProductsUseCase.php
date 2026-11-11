<?php

namespace App\Modules\Catalog\Application\UseCases\Products;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class ListProductsUseCase
{
    public function __construct(
        private CatalogServiceInterface $catalog
    ) {}

    public function execute(
        ListProductsDTO $dto
    ): CursorPaginator {
        return $this->catalog->listProducts($dto);
    }
}
