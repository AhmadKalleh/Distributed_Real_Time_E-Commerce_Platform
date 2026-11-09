<?php

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Presentation\Http\Controllers\CategoryController;
use App\Modules\Catalog\Presentation\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\StickyReadAfterWrite;


Route::prefix('v1/')->middleware(['auth:api', StickyReadAfterWrite::class])->group(function () {
    // ... باقي المسارات كما هي بدون تغيير

    // Categories
        Route::get('categories', [
            CategoryController::class,
            'index',
        ]);

        // Products
        Route::get('products', [
            ProductController::class,
            'index',
        ])->can('viewAny', Product::class);

        Route::get('products/{product}', [
            ProductController::class,
            'show',
        ])->can('viewAny', Product::class);

        Route::post('products', [
            ProductController::class,
            'store',
        ])->can('create', Product::class);
});
