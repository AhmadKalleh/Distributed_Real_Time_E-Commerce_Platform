<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\DTOs\Notifications\ListNotificationsDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\MarkNotificationAsReadDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationServiceInterface
{
    public function listNotifications(ListNotificationsDTO $dto): LengthAwarePaginator;

    public function markAsRead(MarkNotificationAsReadDTO $dto): UserNotification;

    public function send(SendNotificationDTO $dto): UserNotification;
}
