<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class StickyReadAfterWrite
{
    private const TTL_SECONDS = 5;

    public function handle(Request $request, Closure $next)
    {
        $key = 'sticky_write:' . ($request->user()?->id ?? $request->ip());

        // قراءة بعد كتابة حديثة: وجّه للمصدر
        if (Redis::exists($key)) {
            DB::connection()->useWriteConnectionWhenReading();
        }

        $response = $next($request);

        // بعد كتابة ناجحة: سجّل علامة
        if (! $request->isMethodSafe() && $response->isSuccessful()) {
            Redis::setex($key, self::TTL_SECONDS, 1);
        }

        return $response;
    }
}
