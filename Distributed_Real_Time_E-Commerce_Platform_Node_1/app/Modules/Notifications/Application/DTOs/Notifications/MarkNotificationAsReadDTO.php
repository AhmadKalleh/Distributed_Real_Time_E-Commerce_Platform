<?php

namespace App\Modules\Notifications\Application\DTOs\Notifications;

use App\Modules\Notifications\Domain\Entities\UserNotification;

final readonly class MarkNotificationAsReadDTO
{
    public function __construct(public UserNotification $notification) {}
}
