<?php

namespace App\Modules\Orders;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Modules\Orders\Repositories\OrderRepositoryInterface;
use App\Modules\Orders\Repositories\OrderRepository;
class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        Gate::policy(Order::class, OrderPolicy::class);

        RateLimiter::for('orders-create', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute(10)->by('orders:'.$key),
                Limit::perHour(100)->by('orders-h:'.$key),
            ];
        });
        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/routes.php');
    }
}
