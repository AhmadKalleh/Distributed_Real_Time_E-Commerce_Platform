<?php

namespace App\Modules\Orders\Presentation\Http\Policies;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Orders\Domain\Entities\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        // Allowed for everyone, but OrderController scopes customers to their own orders.
        return true;
    }

    /**
     * A customer may only see their own orders; the Responsible sees all.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->isResponsible() || $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /**
     * Only the Responsible can move an order through its lifecycle.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isResponsible();
    }
}
