<?php

namespace App\Modules\Orders\Presentation\Broadcasting;

use App\Modules\Orders\Domain\Events\OrderPlaced;
use App\Modules\Orders\Presentation\Http\Resources\OrderResource;
use Throwable;

final class BroadcastOrderPlaced
{
    public function handle(OrderPlaced $event): void
    {
        try {
            $order = $event->order->loadMissing(['user', 'items.product']);

            event(new OrderPlacedBroadcast(
                (new OrderResource($order))->resolve(),
                ['id' => $order->user->id, 'name' => $order->user->name],
            ));
        } catch (Throwable $exception) {
            // A real-time outage must never fail the order or block the other listeners.
            report($exception);
        }
    }
}
