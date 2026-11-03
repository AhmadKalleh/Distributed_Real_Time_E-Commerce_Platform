<?php

namespace App\Modules\Notifications\Application\DTOs\Notifications;

use App\Modules\Identity\Domain\Entities\User;

final readonly class SendNotificationDTO
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public User $user,
        public string $type,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
