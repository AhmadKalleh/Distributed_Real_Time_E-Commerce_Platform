<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Mail\UserNotificationMail;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Stores an in-app notification and emails a copy to the user.
 */
class NotificationSender
{
    public function send(User $user, string $type, string $title, string $body, array $data = []): UserNotification
    {
        $notification = UserNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        if (config('mail.notifications_enabled')) {
            try {
                Mail::to($user)->queue(new UserNotificationMail($notification));
            } catch (Throwable $e) {
                // A mail outage must not fail the order request; the in-app notification is already saved.
                report($e);
            }
        }

        return $notification;
    }
}
