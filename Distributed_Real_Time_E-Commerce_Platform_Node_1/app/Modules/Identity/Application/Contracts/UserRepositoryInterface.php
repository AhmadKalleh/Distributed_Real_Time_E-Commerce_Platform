<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;
}
