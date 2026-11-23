<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Shared\Contracts\CacheStore;
use App\Shared\Contracts\MessageConsumer;
use App\Shared\Contracts\MessagePublisher;
use App\Shared\Infrastructure\Cache\RedisCacheStore;
use App\Shared\Infrastructure\Messaging\HoldingMessagePublisher;
use App\Shared\Infrastructure\Messaging\RabbitMqConsumer;
use App\Shared\Infrastructure\Messaging\RabbitMqPublisher;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CacheStore::class,RedisCacheStore::class);
        $this->app->singleton(MessagePublisher::class, fn () => new HoldingMessagePublisher(new RabbitMqPublisher()));
        $this->app->singleton(MessageConsumer::class, RabbitMqConsumer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([\App\Shared\Presentation\Console\RelayHeldMessages::class]);
        }
    }
}

