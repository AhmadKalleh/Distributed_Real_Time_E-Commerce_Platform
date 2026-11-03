<?php

namespace App\Modules\Catalog\Infrastructure\Services;

use App\Modules\Catalog\Application\Contracts\CatalogServiceInterface;
use App\Modules\Catalog\Application\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Application\Contracts\ProductImageStorageInterface;
use App\Modules\Catalog\Application\Contracts\ProductCatalogInterface;
use App\Modules\Catalog\Application\Contracts\ProductRepositoryInterface;
use App\Modules\Catalog\Application\DTOs\Products\CreateProductDTO;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use App\Modules\Catalog\Application\DTOs\Products\ShowProductDTO;
use App\Modules\Catalog\Application\DTOs\Products\ProductSnapshot;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Exceptions\CategoryNotFoundException;
use App\Modules\Catalog\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Catalog\Domain\Exceptions\ProductUnavailableException;
use App\Modules\Catalog\Domain\Exceptions\ProductSlugAlreadyExistsException;
use App\Modules\Catalog\Domain\Services\ProductSlugGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

use Throwable;

final readonly class CatalogService implements CatalogServiceInterface, ProductCatalogInterface
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private ProductImageStorageInterface $imageStorage,
        private ProductSlugGenerator $slugs
    ) {}

    public function listCategories(): EloquentCollection
    {
        return $this->categories->all();
    }


    public function listProducts(ListProductsDTO $dto): LengthAwarePaginator
    {
        $filters = $dto->filters;

        $filters['include_inactive'] =
            filter_var(
                $filters['include_inactive'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

        $perPage = max(
            1,
            min(100, (int) ($filters['per_page'] ?? 15))
        );

        return $this->products->paginate($filters, $perPage);
    }



    public function showProduct(ShowProductDTO $dto): Product
    {
        $product = $this->products->findById(
            $dto->productId
        );


        if (
            !$product || !$product->is_active
        ) {
            throw new ProductNotFoundException();
        }

        return $product;
    }



    public function getActiveProductsByIds(array $productIds): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ($productIds === []) {
            return collect();
        }

        $products = $this->products->findByIds($productIds);
        $snapshots = collect();

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if (! $product || ! $product->is_active) {
                $productName = $product?->name ?? "Product #{$productId}";

                throw new ProductUnavailableException(
                    "The product '{$productName}' is currently unavailable or inactive."
                );
            }

            $snapshots->put($productId, new ProductSnapshot(
                id: (int) $product->id,
                name: (string) $product->name,
                price: (string) $product->price,
            ));
        }

        return $snapshots;
    }


    public function createProduct(CreateProductDTO $dto): Product
    {
        if (!$this->categories->findById($dto->categoryId)) {
            throw new CategoryNotFoundException();
        }

        $slug = $this->slugs->fromName($dto->name);
        if ($this->products->slugExists($slug)) {
            throw new ProductSlugAlreadyExistsException();
        }

        return $this->imageStorage->storeAndPersist(
            $dto->images,
            fn (array $stored) => $this->products->create(
                $dto->toProductAttributes($slug),
                $stored,
            ),
        );
    }
}
