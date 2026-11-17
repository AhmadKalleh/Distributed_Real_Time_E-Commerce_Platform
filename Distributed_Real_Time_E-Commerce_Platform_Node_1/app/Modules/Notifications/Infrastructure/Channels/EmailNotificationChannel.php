<?php

namespace App\Modules\Notifications\Infrastructure\Channels;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Application\Contracts\NotificationChannelInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\EmailMessageDTO;
use App\Modules\Notifications\Application\UseCases\DeliverEmailNotificationUseCase;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use App\Shared\Contracts\MessagePublisher;

/** Publishes the email to RabbitMQ and returns immediately. The worker sends it. */
final readonly class EmailNotificationChannel implements NotificationChannelInterface
{
    public function __construct(private MessagePublisher $publisher) {}

    public function deliver(User $recipient, UserNotification $notification): void
    {
        if (! config('mail.notifications_enabled')) {
            return;
        }

        $this->publisher->publish(
            DeliverEmailNotificationUseCase::TOPIC,
            EmailMessageDTO::fromNotification($recipient, $notification)->toArray(),
        );
    }
}
