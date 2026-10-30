<?php

use App\Modules\Catalog\Domain\Entities\Product;
use Illuminate\Support\Facades\Route;
use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use App\Modules\Catalog\Presentation\Http\Controllers\CategoryController;
use App\Modules\Catalog\Presentation\Http\Controllers\ProductController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Feature endpoints are registered by each module under app/Modules/*
| with the /api/v1 prefix. Only cross-cutting routes live here.
|
*/

Route::prefix('v1')->group(function (): void {

    // Public authentication route
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:api')->group(function (): void {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Categories
        Route::get('/categories', [
            CategoryController::class,
            'index',
        ]);

        // Products
        Route::get('/products', [
            ProductController::class,
            'index',
        ])->can('viewAny', Product::class);

        Route::get('/products/{product}', [
            ProductController::class,
            'show',
        ])->can('viewAny', Product::class);

        Route::post('/products', [
            ProductController::class,
            'store',
        ])->can('create', Product::class);

    });
});
