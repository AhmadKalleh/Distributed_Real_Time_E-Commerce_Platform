<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'unread' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return NotificationResource::collection($notifications);
    }

    public function markAsRead(UserNotification $notification): NotificationResource
    {
        $this->authorize('update', $notification);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return new NotificationResource($notification);
    }
}
