<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Services\NotificationSender;
use App\Modules\Orders\Events\OrderStatusChanged;

class NotifyOnOrderStatusChanged
{
    public function __construct(private readonly NotificationSender $sender)
    {
    }

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing('user');

        $this->sender->send(
            $order->user,
            'order.status_changed',
            "Order #{$order->id} is now {$event->to->value}",
            "Your order #{$order->id} changed from {$event->from->value} to {$event->to->value}.",
            [
                'order_id' => $order->id,
                'from' => $event->from->value,
                'to' => $event->to->value,
            ],
        );
    }
}
