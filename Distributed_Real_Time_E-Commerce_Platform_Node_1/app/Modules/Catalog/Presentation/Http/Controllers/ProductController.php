<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Modules\Catalog\Application\Caching\CatalogCacheKeys;
use App\Modules\Catalog\Application\DTOs\Products\CreateProductDTO;
use App\Modules\Catalog\Application\DTOs\Products\UpdateProductDTO;
use App\Modules\Catalog\Application\UseCases\Products\CreateProductUseCase;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use App\Modules\Catalog\Application\DTOs\Products\ShowProductDTO;
use App\Modules\Catalog\Application\UseCases\Products\ListProductsUseCase;
use App\Modules\Catalog\Application\UseCases\Products\ShowProductUseCase;
use App\Modules\Catalog\Presentation\Http\Requests\ListProductsRequest;
use App\Modules\Catalog\Presentation\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Presentation\Http\Resources\ProductResource;
use App\Shared\Contracts\CacheStore;
use App\Shared\Contracts\InventoryGatewayInterface;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ProductController
{
    public function index(
        ListProductsRequest $request,
        ListProductsUseCase $useCase,
        CacheStore $cache,
    ): JsonResponse {
        // Each page (with its filters) has its own key, built under the current list version.
        $key = CatalogCacheKeys::productList(
            $cache->version(CatalogCacheKeys::PRODUCTS_GROUP),
            $request->query(),
        );

        $payload = $cache->remember($key, CatalogCacheKeys::LIST_TTL, function () use ($request, $useCase) {
            /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
            $paginator = $useCase->execute(ListProductsDTO::fromArray($request->validated()));

            return [
                'items' => ProductResource::collection($paginator->getCollection())->resolve(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                    'next_page_url' => $paginator->nextPageUrl(),
                    'prev_page_url' => $paginator->previousPageUrl(),
                ],
            ];
        });

        return ApiResponse::success($payload, 'Products retrieved successfully.');
    }

    public function show(
        int $product,
        ShowProductUseCase $useCase,
        InventoryGatewayInterface $inventory,
        CacheStore $cache,
    ): JsonResponse {
        // Not-found / inactive throws inside the callback, so errors are never cached.
        $data = $cache->remember(
            CatalogCacheKeys::product($product),
            CatalogCacheKeys::PRODUCT_TTL,
            fn () => ProductResource::make($useCase->execute(new ShowProductDTO($product)))->resolve(),
        );

        // Stock comes from another service and must stay real-time: never cached.
        $availableQuantity = $inventory->getAvailableQuantity($product);

        return ApiResponse::success([
            'product' => $data,
            'available_quantity' => $availableQuantity,
        ], 'Product retrieved successfully.');
    }

    public function store(
        StoreProductRequest $request,
        CreateProductUseCase $useCase
    ): JsonResponse
    {
        $dto = CreateProductDTO::fromArray($request->validated());
        $product = $useCase->execute($dto);

        return ApiResponse::success(
            ProductResource::make($product)->resolve(),
            'Product created successfully.',
            201
        );
    }

}
