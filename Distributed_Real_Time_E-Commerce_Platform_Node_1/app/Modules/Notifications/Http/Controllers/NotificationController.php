<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Http\Resources\NotificationResource;
use App\Modules\Notifications\Models\UserNotification;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unread'   => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        // التحقق مما إذا كانت القائمة فارغة لتخصيص الرسالة
        $message = $notifications->isEmpty()
            ? 'No notifications found.'
            : 'Notifications retrieved successfully.';

        return ApiResponse::success([
            'notifications' => NotificationResource::collection($notifications),
            'pagination'    => [
                'current_page'  => $notifications->currentPage(),
                'last_page'     => $notifications->lastPage(),
                'per_page'      => $notifications->perPage(),
                'total'         => $notifications->total(),
                'count'         => $notifications->count(),
                'next_page_url' => $notifications->nextPageUrl(),
                'prev_page_url' => $notifications->previousPageUrl(),
            ]
        ], $message);
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
