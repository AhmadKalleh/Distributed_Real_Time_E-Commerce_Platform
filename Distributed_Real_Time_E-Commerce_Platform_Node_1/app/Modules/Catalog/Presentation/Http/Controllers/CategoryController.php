<?php

namespace App\Modules\Catalog\Presentation\Http\Controllers;

use App\Modules\Catalog\Application\UseCases\Categories\ListCategoriesUseCase;
use App\Modules\Catalog\Presentation\Http\Resources\CategoryResource;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function index(ListCategoriesUseCase $useCase): JsonResponse
    {
        return ApiResponse::success(
            CategoryResource::collection($useCase->execute())->resolve(),
            'Categories retrieved successfully.'
        );
    }
}
