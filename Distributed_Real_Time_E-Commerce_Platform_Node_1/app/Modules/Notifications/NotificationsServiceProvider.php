<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\Listeners\NotifyOnOrderPlaced;
use App\Modules\Notifications\Application\Listeners\NotifyOnOrderStatusChanged;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use App\Modules\Notifications\Infrastructure\Mail\QueuedNotificationMailGateway;
use App\Modules\Notifications\Infrastructure\Persistence\EloquentNotificationRepository;
use App\Modules\Notifications\Infrastructure\Services\NotificationService;
use App\Modules\Notifications\Presentation\Http\Policies\UserNotificationPolicy;
use App\Modules\Orders\Domain\Events\OrderPlaced;
use App\Modules\Orders\Domain\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
        $this->app->bind(NotificationMailGatewayInterface::class, QueuedNotificationMailGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);

        // Notifications reacts to Orders events; Orders never calls Notifications directly.
        Event::listen(OrderPlaced::class, NotifyOnOrderPlaced::class);
        Event::listen(OrderStatusChanged::class, NotifyOnOrderStatusChanged::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Presentation/Http/routes.php');
    }
}
