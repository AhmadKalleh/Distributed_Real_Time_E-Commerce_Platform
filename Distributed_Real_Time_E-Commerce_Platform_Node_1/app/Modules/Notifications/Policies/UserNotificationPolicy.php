<?php

namespace App\Modules\Notifications\Policies;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Models\UserNotification;

class UserNotificationPolicy
{
    /**
     * Notifications are private: not even the Responsible can touch someone else's.
     */
    public function update(User $user, UserNotification $notification): bool
    {
        return $notification->user_id === $user->id;
    }
}
