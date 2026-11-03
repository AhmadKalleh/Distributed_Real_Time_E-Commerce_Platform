<?php

namespace App\Modules\Notifications\Infrastructure\Mail;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class QueuedNotificationMailGateway implements NotificationMailGatewayInterface
{
    public function queue(User $user, UserNotification $notification): void
    {
        if (! config('mail.notifications_enabled')) {
            return;
        }

        try {
            Mail::to($user)->queue(new UserNotificationMail($notification));
        } catch (Throwable $exception) {
            // An SMTP outage must not fail order processing; the in-app item is already saved.
            report($exception);
        }
    }
}
