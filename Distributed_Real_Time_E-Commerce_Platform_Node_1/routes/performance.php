<?php

use App\Http\Controllers\Performance\BaselineController;
use Illuminate\Support\Facades\Route;

Route::get('products', [BaselineController::class, 'products']);
Route::get('products/{product}', [BaselineController::class, 'showProduct'])->whereNumber('product');
Route::get('orders', [BaselineController::class, 'orders']);
Route::get('orders/{order}', [BaselineController::class, 'showOrder'])->whereNumber('order');
