<?php

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Application\Contracts\UserRepositoryInterface;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }
}
