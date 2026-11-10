<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Application\Contracts\ProductImageStorageInterface;
use App\Modules\Catalog\Application\Contracts\ProductCatalogInterface;
use App\Modules\Catalog\Application\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Entities\Category;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use App\Modules\Catalog\Infrastructure\Services\CatalogService;
use App\Modules\Catalog\Infrastructure\Storage\LocalProductImageStorage;
use App\Modules\Catalog\Presentation\Http\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Modules\Catalog\Infrastructure\Caching\CategoryCacheObserver;
use App\Modules\Catalog\Infrastructure\Caching\ProductCacheObserver;


final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CatalogServiceInterface::class, CatalogService::class);
        $this->app->bind(ProductCatalogInterface::class, CatalogService::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ProductImageStorageInterface::class, LocalProductImageStorage::class);
    }

    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);

        Product::observe(ProductCacheObserver::class);
        Category::observe(CategoryCacheObserver::class);
    }
}
