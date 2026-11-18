<?php

namespace App\Modules\Orders\Presentation\Broadcasting;

use App\Modules\Orders\Domain\Events\OrderStatusChanged;
use App\Modules\Orders\Presentation\Http\Resources\OrderResource;
use Throwable;

final class BroadcastOrderStatusChanged
{
    public function handle(OrderStatusChanged $event): void
    {
        try {
            $order = $event->order->loadMissing('items.product');

            event(new OrderStatusChangedBroadcast(
                $order->id,
                (new OrderResource($order))->resolve(),
                $event->from->value,
                $event->to->value,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
