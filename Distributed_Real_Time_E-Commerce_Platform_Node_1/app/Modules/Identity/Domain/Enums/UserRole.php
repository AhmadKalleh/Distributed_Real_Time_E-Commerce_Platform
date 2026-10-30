<?php

namespace App\Modules\Identity\Domain\Enums;

enum UserRole: string
{
    case CUSTOMER = 'Customer';
    case RESPONSIBLE = 'Responsible';
}
