<?php

use App\Modules\Orders\Presentation\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:orders-create');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order');
});
