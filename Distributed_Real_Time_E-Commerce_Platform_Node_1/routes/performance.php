<?php

use App\Http\Controllers\Performance\BaselineController;
use App\Http\Controllers\Performance\OptimizedPerformanceController;
use App\Http\Middleware\EnsurePerformanceTesting;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsurePerformanceTesting::class)
    ->prefix('performance')
    ->group(function () {
        // قبل التحسين: /api/performance/baseline/...
        Route::prefix('baseline')->group(function () {
            Route::get('products', [BaselineController::class, 'products']);
            Route::get('products/{product}', [BaselineController::class, 'showProduct'])->whereNumber('product');
            Route::get('orders', [BaselineController::class, 'orders']);
            Route::get('orders/{order}', [BaselineController::class, 'showOrder'])->whereNumber('order');
        });

                // Optimized Eloquent endpoints
        Route::prefix('optimized')->group(function () {
            Route::get('products', [
                OptimizedPerformanceController::class,
                'products'
            ]);

            Route::get('products/{product}', [
                OptimizedPerformanceController::class,
                'showProduct'
            ])->whereNumber('product');

            Route::get('orders', [
                OptimizedPerformanceController::class,
                'orders'
            ]);

            Route::get('orders/{order}', [
                OptimizedPerformanceController::class,
                'showOrder'
            ])->whereNumber('order');
        });
    });
