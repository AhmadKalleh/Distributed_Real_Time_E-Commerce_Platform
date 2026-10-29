<?php

namespace App\Modules\Products\Policies;

use App\Models\User;

class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->isResponsible();
    }
}
