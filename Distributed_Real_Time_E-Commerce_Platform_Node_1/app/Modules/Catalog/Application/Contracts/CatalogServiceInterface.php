<?php

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Application\DTOs\Products\CreateProductDTO;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use App\Modules\Catalog\Application\DTOs\Products\ShowProductDTO;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

interface CatalogServiceInterface
{
    public function listCategories(): Collection;

    public function listProducts(ListProductsDTO $dto): CursorPaginator;

    public function showProduct(ShowProductDTO $dto): Product;

    public function createProduct(CreateProductDTO $dto): Product;

}
