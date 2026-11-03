<?php

namespace App\Modules\Catalog\Presentation\Http\Policies;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Identity\Domain\Enums\UserRole;
use App\Modules\Identity\Domain\Entities\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::RESPONSIBLE->value;
    }


}
