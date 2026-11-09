<?php

namespace App\Modules\Catalog\Infrastructure\Caching;

use App\Modules\Catalog\Application\Caching\CatalogCacheKeys;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Shared\Contracts\CacheStore;

/**
 * Runs only after the DB transaction commits, so a reader can never
 * re-cache old data between the invalidation and the commit.
 */
final class ProductCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly CacheStore $cache) {}

    public function created(Product $product): void
    {
        $this->cache->bump(CatalogCacheKeys::PRODUCTS_GROUP);
    }

    public function updated(Product $product): void
    {
        $this->invalidate($product);
    }

    public function deleted(Product $product): void
    {
        $this->invalidate($product);
    }

    public function restored(Product $product): void
    {
        $this->invalidate($product);
    }

    private function invalidate(Product $product): void
    {
        $this->cache->forget(CatalogCacheKeys::product((int) $product->id));
        $this->cache->bump(CatalogCacheKeys::PRODUCTS_GROUP);
    }
}
