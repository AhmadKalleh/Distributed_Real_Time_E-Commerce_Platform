<?php

namespace App\Modules\Notifications\Infrastructure\Services;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\ListNotificationsDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\MarkNotificationAsReadDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Implements notification workflows using Notifications-owned ports. */
final readonly class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private NotificationRepositoryInterface $notifications,
        private NotificationMailGatewayInterface $mail,
    ) {}

    public function listNotifications(ListNotificationsDTO $dto): LengthAwarePaginator
    {
        return $this->notifications->paginateForUser(
            $dto->userId,
            $dto->unreadOnly,
            $dto->perPage,
        );
    }

    public function markAsRead(MarkNotificationAsReadDTO $dto): UserNotification
    {
        if ($dto->notification->read_at) {
            return $dto->notification;
        }

        return $this->notifications->markAsRead($dto->notification);
    }

    public function send(SendNotificationDTO $dto): UserNotification
    {
        $notification = $this->notifications->create(
            $dto->user->id,
            $dto->type,
            $dto->title,
            $dto->body,
            $dto->data,
        );

        // Email is queued after the in-app notification is persisted.
        $this->mail->queue($dto->user, $notification);

        return $notification;
    }
}
