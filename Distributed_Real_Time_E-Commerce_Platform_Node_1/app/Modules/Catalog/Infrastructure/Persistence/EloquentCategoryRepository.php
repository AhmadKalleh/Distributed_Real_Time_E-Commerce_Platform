<?php

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Application\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Entities\Category;
use Illuminate\Database\Eloquent\Collection;

final class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function all(): Collection
    {
        return Category::query()
            ->select(['id', 'name', 'slug', 'description'])
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Category
    {
        return Category::query()->find($id);
    }
}
