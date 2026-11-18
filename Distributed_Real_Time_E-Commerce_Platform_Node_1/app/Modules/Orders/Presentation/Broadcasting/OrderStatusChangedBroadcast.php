<?php

namespace App\Modules\Orders\Presentation\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Status change, pushed to the order tracking page. Channel: private-orders.{id} (owner only). */
final class OrderStatusChangedBroadcast implements ShouldBroadcastNow
{
    /** @param array<string, mixed> $order same shape as the order in the REST API */
    public function __construct(
        public readonly int $orderId,
        public readonly array $order,
        public readonly string $from,
        public readonly string $to,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders.' . $this->orderId)];
    }

    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order' => $this->order, 'from' => $this->from, 'to' => $this->to];
    }
}
