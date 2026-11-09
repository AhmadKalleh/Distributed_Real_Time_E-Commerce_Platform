<?php

namespace App\Modules\Catalog\Infrastructure\Caching;

use App\Modules\Catalog\Application\Caching\CatalogCacheKeys;
use App\Modules\Catalog\Domain\Entities\Category;
use App\Shared\Contracts\CacheStore;

final class CategoryCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly CacheStore $cache) {}

    public function saved(Category $category): void
    {
        $this->invalidate();
    }

    public function deleted(Category $category): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        $this->cache->forget(CatalogCacheKeys::CATEGORIES);
        // product payloads embed the category name
        $this->cache->bump(CatalogCacheKeys::PRODUCTS_GROUP);
    }
}
