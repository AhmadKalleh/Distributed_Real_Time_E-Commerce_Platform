<?php

use App\Modules\Identity\Presentation\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // Public authentication route
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::middleware('auth:api')->group(function (): void {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

    });
});
