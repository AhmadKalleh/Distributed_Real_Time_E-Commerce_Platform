<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationSender;
use App\Modules\Orders\Events\OrderPlaced;
use App\Modules\Users\Enums\Role;

class NotifyOnOrderPlaced
{
    public function __construct(private readonly NotificationSender $sender)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing('user');
        $data = ['order_id' => $order->id, 'total_amount' => $order->total_amount];

        $this->sender->send(
            $order->user,
            'order.placed',
            "Order #{$order->id} received",
            "We received your order #{$order->id} ({$order->total_amount}). It is now Pending.",
            $data,
        );

        User::where('role', Role::Responsible)->get()->each(fn (User $admin) => $this->sender->send(
            $admin,
            'order.placed',
            "New order #{$order->id}",
            "A customer placed order #{$order->id} ({$order->total_amount}).",
            $data,
        ));
    }
}
