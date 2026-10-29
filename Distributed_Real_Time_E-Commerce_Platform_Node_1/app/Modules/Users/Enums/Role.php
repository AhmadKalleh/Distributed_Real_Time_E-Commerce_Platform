<?php

namespace App\Modules\Users\Enums;

enum Role: string
{
    /** Buys products and sees only their own orders. */
    case Customer = 'Customer';

    /** Store administrator: manages products and is the only one who changes order status. */
    case Responsible = 'Responsible';
}
