<?php

namespace App\Modules\Notifications\Application\DTOs\Notifications;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Domain\Entities\UserNotification;

/**
 * Everything the worker needs to send one email.
 * The message is self-contained, so the worker never reads the database.
 */
final readonly class EmailMessageDTO
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public int $notificationId,
        public string $toEmail,
        public string $toName,
        public string $type,
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $createdAt = null,
    ) {}

    public static function fromNotification(User $recipient, UserNotification $notification): self
    {
        return new self(
            $notification->id,
            $recipient->email,
            (string) $recipient->name,
            $notification->type,
            $notification->title,
            $notification->body,
            $notification->data ?? [],
            $notification->created_at?->toIso8601String(),
        );
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            (int) $payload['notification_id'],
            $payload['to_email'],
            (string) ($payload['to_name'] ?? ''),
            $payload['type'],
            $payload['title'],
            $payload['body'],
            $payload['data'] ?? [],
            $payload['created_at'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'notification_id' => $this->notificationId,
            'to_email' => $this->toEmail,
            'to_name' => $this->toName,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'created_at' => $this->createdAt,
        ];
    }
}
