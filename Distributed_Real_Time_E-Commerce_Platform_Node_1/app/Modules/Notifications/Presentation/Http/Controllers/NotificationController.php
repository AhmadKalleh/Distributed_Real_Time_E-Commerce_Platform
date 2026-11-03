<?php

namespace App\Modules\Notifications\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Presentation\Http\Resources\NotificationResource;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use App\Shared\Http\Responses\ApiResponse;
use App\Modules\Notifications\Application\UseCases\ListNotificationsUseCase;
use App\Modules\Notifications\Application\UseCases\MarkNotificationAsReadUseCase;
use App\Modules\Notifications\Application\DTOs\Notifications\ListNotificationsDTO;
use App\Modules\Notifications\Application\DTOs\Notifications\MarkNotificationAsReadDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class NotificationController extends Controller
{
    public function index(Request $request, ListNotificationsUseCase $useCase): JsonResponse
    {
        $validated = $request->validate([
            'unread'   => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $notifications = $useCase->execute(new ListNotificationsDTO(
            userId: (int) $request->user()->id,
            unreadOnly: $request->boolean('unread'),
            perPage: (int) ($validated['per_page'] ?? 20),
        ));

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

    public function markAsRead(UserNotification $notification, MarkNotificationAsReadUseCase $useCase): NotificationResource
    {
        $this->authorize('update', $notification);

        return new NotificationResource($useCase->execute(new MarkNotificationAsReadDTO($notification)));
    }
}
