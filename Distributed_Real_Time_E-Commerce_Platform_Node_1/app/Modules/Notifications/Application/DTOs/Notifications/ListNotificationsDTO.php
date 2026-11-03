<?php

namespace App\Modules\Notifications\Application\DTOs\Notifications;

final readonly class ListNotificationsDTO
{
    public function __construct(
        public int $userId,
        public bool $unreadOnly,
        public int $perPage,
    ) {}
}
