<?php

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Domain\Entities\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly Order $order)
    {
    }
}
