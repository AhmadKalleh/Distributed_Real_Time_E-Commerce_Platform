<?php

namespace App\Modules\Notifications\Infrastructure\Persistence;

use App\Modules\Notifications\Application\Contracts\NotificationRepositoryInterface;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function paginateForUser(int $userId, bool $unreadOnly, int $perPage): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): UserNotification {
        return UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function markAsRead(UserNotification $notification): UserNotification
    {
        $notification->update(['read_at' => now()]);

        return $notification;
    }
}
