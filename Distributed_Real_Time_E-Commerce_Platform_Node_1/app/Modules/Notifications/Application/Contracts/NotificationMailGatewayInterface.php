<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Domain\Entities\UserNotification;

interface NotificationMailGatewayInterface
{
    public function queue(User $user, UserNotification $notification): void;
}
