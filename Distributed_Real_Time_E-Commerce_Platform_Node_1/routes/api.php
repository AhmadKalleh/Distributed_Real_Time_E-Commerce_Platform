<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Feature endpoints are registered by each module under app/Modules/*
| with the /api/v1 prefix. Only cross-cutting routes live here.
|
*/

Route::get('v1/health', fn () => response()->json([
    'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
]));
