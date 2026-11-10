<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Modules\Catalog\Application\Caching\CatalogCacheKeys;
use App\Modules\Catalog\Application\UseCases\Categories\ListCategoriesUseCase;
use App\Modules\Catalog\Presentation\Http\Resources\CategoryResource;
use App\Shared\Contracts\CacheStore;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(ListCategoriesUseCase $useCase, CacheStore $cache): JsonResponse
    {
        $data = $cache->remember(
            CatalogCacheKeys::CATEGORIES,
            CatalogCacheKeys::CATEGORIES_TTL,
            fn () => CategoryResource::collection($useCase->execute())->resolve(),
        );

        return ApiResponse::success($data, 'Categories retrieved successfully.');
    }
}
