<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Shared\Contracts\CacheStore;
use App\Shared\Infrastructure\Cache\RedisCacheStore;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CacheStore::class,RedisCacheStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
