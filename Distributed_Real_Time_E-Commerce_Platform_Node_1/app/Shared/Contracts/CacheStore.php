<?php

namespace App\Shared\Contracts;

use Closure;

interface CacheStore
{
    /** يرجع القيمة المخزنة أو ينفّذ الـ callback ويخزّن نتيجته. */
    public function remember(string $key, int $ttlSeconds, Closure $callback): mixed;

    public function forget(string ...$keys): void;

    /** رقم نسخة لمجموعة مفاتيح (قوائم). */
    public function version(string $group): int;

    /** رفع النسخة = إبطال كل مفاتيح المجموعة. */
    public function bump(string $group): void;
}
