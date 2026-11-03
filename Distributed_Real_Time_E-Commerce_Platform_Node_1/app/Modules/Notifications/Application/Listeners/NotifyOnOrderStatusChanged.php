<?php

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Notifications\Application\DTOs\Notifications\SendNotificationDTO;
use App\Modules\Notifications\Application\UseCases\SendNotificationUseCase;
use App\Modules\Orders\Domain\Events\OrderStatusChanged;

final class NotifyOnOrderStatusChanged
{
    public function __construct(private readonly SendNotificationUseCase $sender) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('user');

        $this->sender->execute(new SendNotificationDTO(
            user: $order->user,
            type: 'order.status_changed',
            title: "Order #{$order->id} is now {$event->to->value}",
            body: "Your order #{$order->id} changed from {$event->from->value} to {$event->to->value}.",
            data: [
                'order_id' => $order->id,
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
        ));
    }
}
