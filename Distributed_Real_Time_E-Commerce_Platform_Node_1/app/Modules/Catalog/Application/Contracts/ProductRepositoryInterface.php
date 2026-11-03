<?php

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Domain\Entities\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;
    public function findById(int $id): ?Product;
    public function slugExists(string $slug): bool;
    public function create(array $attributes, array $images = []): Product;
}
