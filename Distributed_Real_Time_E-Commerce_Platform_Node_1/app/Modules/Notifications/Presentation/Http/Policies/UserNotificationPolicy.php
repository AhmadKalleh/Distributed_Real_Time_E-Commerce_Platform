<?php

namespace App\Modules\Notifications\Presentation\Http\Policies;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Domain\Entities\UserNotification;

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
