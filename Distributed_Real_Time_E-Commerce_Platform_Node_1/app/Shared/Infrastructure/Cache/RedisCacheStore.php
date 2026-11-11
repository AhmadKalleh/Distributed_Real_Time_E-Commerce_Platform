<?php

namespace App\Shared\Infrastructure\Cache;

use App\Shared\Contracts\CacheStore;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class RedisCacheStore implements CacheStore
{
    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        try {
            //التحقق من وجود البيانات في الكاش (Cache Hit):
            $hit = Redis::get($key);
            if ($hit !== null && $hit !== false) {
                return json_decode($hit, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (Throwable $e) {
            //التعامل مع سقوط الـ Redis (Fallback):
            Log::warning('cache read failed, falling back to DB', ['key' => $key, 'error' => $e->getMessage()]);
            return $callback();
        }

        // الملء من المصدر لتجنّب تخزين قيمة قديمة من نسخة متأخرة
        DB::connection()->useWriteConnectionWhenReading();
        try {
            $value = $callback();
        } finally {
            DB::connection()->useWriteConnectionWhenReading(false);
        }

        //Cache Stampede Protection
        try {
            $ttl = $ttlSeconds + random_int(0, (int) ($ttlSeconds * 0.1));
            Redis::setex($key, $ttl, json_encode($value, JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            Log::warning('cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        return $value;
    }

    public function forget(string ...$keys): void
    {
        try {
            if ($keys !== []) {
                Redis::del(...$keys);
            }
        } catch (Throwable $e) {
            Log::warning('cache forget failed', ['keys' => $keys, 'error' => $e->getMessage()]);
        }
    }

    public function version(string $group): int
    {
        try {
            return (int) Redis::get("ver:{$group}");
        } catch (Throwable $e) {
            Log::warning('cache version read failed', ['group' => $group, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public function bump(string $group): void
    {
        try {
            Redis::incr("ver:{$group}");
        } catch (Throwable $e) {
            Log::warning('cache bump failed', ['group' => $group, 'error' => $e->getMessage()]);
        }
    }

    public function has(string $key): bool
    {
        try {
            return (bool) Redis::exists($key);
        } catch (Throwable $e) {
            Log::warning('cache exists check failed, falling back', ['key' => $key, 'error' => $e->getMessage()]);
            return false; // في حال سقوط اليديس، نعتبر أنه غير موجود لتجنب توقف التطبيق
        }
    }

    public function put(string $key, mixed $value, int $ttlSeconds): void
    {
        try {
            $payload = is_scalar($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
            Redis::setex($key, $ttlSeconds, $payload);
        } catch (Throwable $e) {
            Log::warning('cache put failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }
}
