<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function paginateForUser(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator;

    public function create(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): UserNotification;

    public function markAsRead(UserNotification $notification): UserNotification;
}
