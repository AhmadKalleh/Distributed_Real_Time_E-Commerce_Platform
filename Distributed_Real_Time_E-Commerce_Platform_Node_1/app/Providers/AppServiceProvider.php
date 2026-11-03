<?php

namespace App\Providers;
use App\Modules\Identity\Application\Contracts\JwtAuthenticationContract;
use App\Modules\Identity\Application\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Infrastructure\Authentication\JwtAuthenticationService;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use App\Modules\Catalog\Application\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Infrastructure\Services\CatalogService;
use App\Modules\Catalog\Application\Contracts\ProductImageStorageInterface;
use App\Modules\Catalog\Application\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use App\Modules\Catalog\Infrastructure\Storage\LocalProductImageStorage;
use App\Modules\Catalog\Presentation\Http\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
        UserRepositoryInterface::class,
        EloquentUserRepository::class
        );

        $this->app->bind(
            JwtAuthenticationContract::class,
            JwtAuthenticationService::class
        );

        $this->app->bind(CatalogServiceInterface::class, CatalogService::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ProductImageStorageInterface::class, LocalProductImageStorage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
    }
}
