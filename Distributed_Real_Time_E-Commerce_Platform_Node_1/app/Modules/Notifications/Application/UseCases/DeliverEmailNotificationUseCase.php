<?php

namespace App\Modules\Notifications\Application\UseCases;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\EmailMessageDTO;
use Illuminate\Support\Facades\Log;

/** Worker side: turns a RabbitMQ message into a real email. Throws on failure so it is retried. */
final readonly class DeliverEmailNotificationUseCase
{
    /** RabbitMQ topic shared by the publisher (EmailNotificationChannel) and the worker. */
    public const TOPIC = 'notification.email';

    public function __construct(private NotificationMailGatewayInterface $mail) {}

    /** @param array<string, mixed> $payload */
    public function execute(array $payload): void
    {
        $email = EmailMessageDTO::fromArray($payload);

        $this->mail->send($email);

        Log::info('Email notification sent.', [
            'notification_id' => $email->notificationId,
            'to' => $email->toEmail,
        ]);
    }
}
