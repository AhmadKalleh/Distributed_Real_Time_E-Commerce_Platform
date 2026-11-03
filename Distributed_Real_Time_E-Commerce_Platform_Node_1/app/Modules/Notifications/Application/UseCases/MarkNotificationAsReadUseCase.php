<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\MarkNotificationAsReadDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;

final readonly class MarkNotificationAsReadUseCase
{
    public function __construct(private NotificationServiceInterface $notifications) {}

    public function execute(MarkNotificationAsReadDTO $dto): UserNotification
    {
        return $this->notifications->markAsRead($dto);
    }
}
