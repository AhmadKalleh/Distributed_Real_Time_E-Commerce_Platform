<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Listeners\NotifyOnOrderPlaced;
use App\Modules\Notifications\Listeners\NotifyOnOrderStatusChanged;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Policies\UserNotificationPolicy;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Orders\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);

        // The Orders module never calls this module directly; it only fires events.
        Event::listen(OrderPlaced::class, NotifyOnOrderPlaced::class);
        Event::listen(OrderStatusChanged::class, NotifyOnOrderStatusChanged::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(__DIR__.'/routes.php');
    }
}
