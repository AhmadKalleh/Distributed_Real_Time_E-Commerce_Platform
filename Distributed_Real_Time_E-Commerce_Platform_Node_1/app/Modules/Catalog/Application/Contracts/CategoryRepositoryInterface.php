<?php

namespace App\Modules\Catalog\Application\Contracts;

use App\Modules\Catalog\Domain\Entities\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    public function all(): Collection;
    public function findById(int $id): ?Category;
}
