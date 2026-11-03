<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Domain\Enums\UserRole;
use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Application\UseCases\SendNotificationUseCase;
use App\Modules\Orders\Domain\Events\OrderPlaced;

final class NotifyOnOrderPlaced
{
    public function __construct(private readonly SendNotificationUseCase $sender) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('user');
        $data = ['order_id' => $order->id, 'total_amount' => $order->total_amount];

        $this->sender->execute(new SendNotificationDTO(
            user: $order->user,
            type: 'order.placed',
            title: "Order #{$order->id} received",
            body: "We received your order #{$order->id} ({$order->total_amount}). It is now Pending.",
            data: $data,
        ));

        User::where('role', UserRole::RESPONSIBLE)->get()->each(function (User $admin) use ($order, $data): void {
            $this->sender->execute(new SendNotificationDTO(
                user: $admin,
                type: 'order.placed',
                title: "New order #{$order->id}",
                body: "A customer placed order #{$order->id} ({$order->total_amount}).",
                data: $data,
            ));
        });
    }
}
