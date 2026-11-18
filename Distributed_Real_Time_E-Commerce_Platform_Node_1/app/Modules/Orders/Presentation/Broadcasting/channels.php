<?php

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Support\Facades\Broadcast;

// Both channels need a valid JWT (auth:api) before this callback runs.
// The callbacks then decide whether this user may listen.

// Admin dashboard: role check, only the Responsible.
Broadcast::channel(
    'admin.orders',
    fn (User $user) => $user->isResponsible(),
    ['guards' => ['api']],
);

// Order tracking page: ownership check, only the customer who owns the order.
// Read from the primary: the customer usually subscribes right after placing the order,
// and the replica may not have it yet.
Broadcast::channel(
    'orders.{orderId}',
    fn (User $user, $orderId) => Order::onWriteConnection()
        ->whereKey($orderId)
        ->where('user_id', $user->id)
        ->exists(),
    ['guards' => ['api']],
);
