<?php

namespace App\Modules\Orders;

use App\Modules\Orders\Application\Contracts\OrderRepositoryInterface;
use App\Modules\Orders\Application\Contracts\OrderServiceInterface;
use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Infrastructure\Grpc\InventoryGrpcClient;
use App\Modules\Orders\Infrastructure\Persistence\EloquentOrderRepository;
use App\Modules\Orders\Infrastructure\Services\OrderService;
use App\Modules\Orders\Presentation\Http\Policies\OrderPolicy;
use App\Shared\Contracts\InventoryGatewayInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(OrderServiceInterface::class, OrderService::class);
        $this->app->bind(InventoryGatewayInterface::class, InventoryGrpcClient::class);
    }

    public function boot(): void
    {
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
            ->group(__DIR__.'/Presentation/Http/routes.php');
    }
}
