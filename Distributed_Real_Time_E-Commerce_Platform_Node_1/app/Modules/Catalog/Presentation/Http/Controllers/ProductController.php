<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\Products\CreateProductDTO;
use App\Modules\Catalog\Application\DTOs\Products\UpdateProductDTO;
use App\Modules\Catalog\Application\UseCases\Products\CreateProductUseCase;
use App\Modules\Catalog\Application\DTOs\Products\ListProductsDTO;
use App\Modules\Catalog\Application\DTOs\Products\ShowProductDTO;
use App\Modules\Catalog\Application\UseCases\Products\ListProductsUseCase;
use App\Modules\Catalog\Application\UseCases\Products\ShowProductUseCase;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Presentation\Http\Requests\ListProductsRequest;
use App\Modules\Catalog\Presentation\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Presentation\Http\Resources\ProductResource;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ProductController
{
    public function index(ListProductsRequest $request,ListProductsUseCase $useCase): JsonResponse
    {

        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $useCase->execute(
            ListProductsDTO::fromArray(
                $request->validated()
            )
        );

        return ApiResponse::success([
            'items' => ProductResource::collection(
                $paginator->getCollection()
            )->resolve(),

            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
            ],
        ], 'Products retrieved successfully.');
    }

    public function show(int $product, ShowProductUseCase $useCase): JsonResponse
    {
        $found = $useCase->execute(new ShowProductDTO($product));

        return ApiResponse::success(
            ProductResource::make($found)->resolve(),
            'Product retrieved successfully.'
        );
    }

    public function store(StoreProductRequest $request,CreateProductUseCase $useCase): JsonResponse
    {
        $product = $useCase->execute(
            CreateProductDTO::fromArray($request->validated())
        );

        return ApiResponse::success(
            ProductResource::make($product)->resolve(),
            'Product created successfully.',
            201
        );
    }

}
