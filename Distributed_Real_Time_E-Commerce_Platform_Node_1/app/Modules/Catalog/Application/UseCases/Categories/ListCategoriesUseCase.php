<?php

namespace App\Modules\Catalog\Application\UseCases\Categories;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use Illuminate\Database\Eloquent\Collection;

final readonly class ListCategoriesUseCase
{
    public function __construct(private CatalogServiceInterface $catalog) {}

    public function execute(): Collection
    {
        return $this->catalog->listCategories();
    }
}
