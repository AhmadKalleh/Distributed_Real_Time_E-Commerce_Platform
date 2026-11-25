<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\EmailMessageDTO;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Worker side: turns a RabbitMQ message into a real email. Throws on failure so it is retried. */
final readonly class DeliverEmailNotificationUseCase
{
    /** RabbitMQ topic shared by the publisher (EmailNotificationChannel) and the worker. */
    public const TOPIC = 'notification.email';

    public function __construct(
        private NotificationMailGatewayInterface $mail,
        private NotificationRepositoryInterface $notifications,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(array $payload): void
    {
        $email = EmailMessageDTO::fromArray($payload);

        if (! $this->notifications->reserveEmailDelivery($email->notificationId)) {
            Log::info('Email notification was already sent or is being processed.', [
                'notification_id' => $email->notificationId,
            ]);

            return;
        }

        try {
            $this->mail->send($email);
        } catch (Throwable $exception) {
            $this->notifications->releaseEmailDelivery($email->notificationId);

            throw $exception;
        }

        $this->notifications->markEmailDeliverySent($email->notificationId);

        Log::info('Email notification sent.', [
            'notification_id' => $email->notificationId,
            'to' => $email->toEmail,
        ]);
    }
}
