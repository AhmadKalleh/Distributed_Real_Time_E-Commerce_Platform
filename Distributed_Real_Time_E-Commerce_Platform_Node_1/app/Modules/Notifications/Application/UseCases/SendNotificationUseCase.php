<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;

final readonly class SendNotificationUseCase
{
    public function __construct(private NotificationServiceInterface $notifications) {}

    public function execute(SendNotificationDTO $dto): UserNotification
    {
        return $this->notifications->send($dto);
    }
}
