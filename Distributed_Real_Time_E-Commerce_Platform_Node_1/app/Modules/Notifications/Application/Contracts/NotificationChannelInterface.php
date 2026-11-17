<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Domain\Entities\UserNotification;

/**
 * A way to deliver a saved notification (email, SMS, push...).
 * To add a new way: implement this and register it in NotificationsServiceProvider.
 * NotificationService itself never changes.
 */
interface NotificationChannelInterface
{
    public function deliver(User $recipient, UserNotification $notification): void;
}
