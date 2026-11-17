<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\Listeners\NotifyOnOrderPlaced;
use App\Modules\Notifications\Application\Listeners\NotifyOnOrderStatusChanged;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use App\Modules\Notifications\Infrastructure\Channels\EmailNotificationChannel;
use App\Modules\Notifications\Infrastructure\Mail\SmtpNotificationMailGateway;
use App\Modules\Notifications\Infrastructure\Persistence\EloquentNotificationRepository;
use App\Modules\Notifications\Infrastructure\Services\NotificationService;
use App\Modules\Notifications\Presentation\Console\ConsumeEmailNotifications;
use App\Modules\Notifications\Presentation\Http\Policies\UserNotificationPolicy;
use App\Modules\Orders\Domain\Events\OrderPlaced;
use App\Modules\Orders\Domain\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    /** Every way a notification is delivered. Add a new channel class here, nothing else changes. */
    private const CHANNELS = [
        EmailNotificationChannel::class,
    ];

    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationMailGatewayInterface::class, SmtpNotificationMailGateway::class);

        $this->app->tag(self::CHANNELS, 'notification.channels');
        $this->app->bind(NotificationServiceInterface::class, fn ($app) => new NotificationService(
            $app->make(NotificationRepositoryInterface::class),
            $app->tagged('notification.channels'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);

        // Notifications reacts to Orders events; Orders never calls Notifications directly.
        Event::listen(OrderPlaced::class, NotifyOnOrderPlaced::class);
        Event::listen(OrderStatusChanged::class, NotifyOnOrderStatusChanged::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ConsumeEmailNotifications::class]);
        }

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/Presentation/Http/routes.php');
    }
}
