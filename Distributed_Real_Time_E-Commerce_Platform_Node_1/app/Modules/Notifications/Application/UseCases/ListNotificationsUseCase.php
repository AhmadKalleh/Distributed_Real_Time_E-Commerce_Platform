<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\ListNotificationsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListNotificationsUseCase
{
    public function __construct(private NotificationServiceInterface $notifications) {}

    public function execute(ListNotificationsDTO $dto): LengthAwarePaginator
    {
        return $this->notifications->listNotifications($dto);
    }
}
