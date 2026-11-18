<?php

namespace App\Modules\Orders\Presentation\Broadcasting;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** New order, pushed to the admin dashboard. Channel: private-admin.orders (Responsible only). */
final class OrderPlacedBroadcast implements ShouldBroadcastNow
{
    /**
     * @param array<string, mixed> $order    same shape as the order in the REST API
     * @param array<string, mixed> $customer
     */
    public function __construct(
        public readonly array $order,
        public readonly array $customer,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['order' => $this->order, 'customer' => $this->customer];
    }
}
