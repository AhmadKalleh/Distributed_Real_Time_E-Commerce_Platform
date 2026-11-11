<?php

namespace App\Http\Middleware;

use App\Shared\Contracts\CacheStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class StickyReadAfterWrite
{
    private const TTL_SECONDS = 5;

    public function __construct(
        private readonly CacheStore $cacheStore
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'sticky_write:' . ($request->user()?->id ?? $request->ip());

        // قراءة بعد كتابة حديثة: وجّه للمصدر باستخدام الخدمة المجهزة
        if ($this->cacheStore->has($key)) {
            DB::connection()->useWriteConnectionWhenReading();
        }

        $response = $next($request);

        // بعد كتابة ناجحة: سجّل علامة باستخدام الخدمة
        if (! $request->isMethodSafe() && $response->isSuccessful()) {
            $this->cacheStore->put($key, 1, self::TTL_SECONDS);
        }

        return $response;
    }
}
