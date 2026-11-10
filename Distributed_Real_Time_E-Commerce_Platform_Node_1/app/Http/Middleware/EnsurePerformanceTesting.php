<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePerformanceTesting
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('app.performance_testing') && !app()->environment('production'),
            404
        );

        return $next($request);
    }
}
