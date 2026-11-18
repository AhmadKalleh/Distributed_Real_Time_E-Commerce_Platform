<?php

namespace App\Modules\Notifications\Infrastructure\Services;

use App\Modules\Notifications\Application\Contracts\NotificationChannelInterface;
use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Application\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\ListNotificationsDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\MarkNotificationAsReadDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Throwable;

/**
 * Implements notification workflows.
 * Open for extension: new delivery ways are added as NotificationChannelInterface
 * implementations, without touching this class (Open/Closed Principle).
 */
final readonly class NotificationService implements NotificationServiceInterface
{
    /** @param iterable<NotificationChannelInterface> $channels */
    public function __construct(
        private NotificationRepositoryInterface $notifications,
        private iterable $channels,
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

        // The in-app notification is saved. Each channel delivers it on its own:
        // a broker or SMTP outage must never fail order processing nor block another channel.
        foreach ($this->channels as $channel) {
            try {
                $channel->deliver($dto->user, $notification);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $notification;
    }
}
