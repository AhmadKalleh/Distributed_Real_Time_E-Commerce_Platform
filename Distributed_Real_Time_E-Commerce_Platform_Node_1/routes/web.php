<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/docs');

// Interactive API documentation (Swagger UI) and the raw OpenAPI spec.
Route::view('/docs', 'docs');
Route::get('/docs/openapi.yaml', fn () => response(file_get_contents(base_path('docs/openapi.yaml')), 200, [
    'Content-Type' => 'application/yaml; charset=UTF-8',
]));
