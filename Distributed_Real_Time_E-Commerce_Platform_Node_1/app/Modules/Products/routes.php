<?php

use App\Modules\Products\Http\Controllers\CategoryController;
use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Public catalogue.
Route::get('categories', [CategoryController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');

// Admin only (enforced by ProductPolicy::create).
Route::middleware('auth:api')->group(function () {
    Route::post('products', [ProductController::class, 'store']);
});
