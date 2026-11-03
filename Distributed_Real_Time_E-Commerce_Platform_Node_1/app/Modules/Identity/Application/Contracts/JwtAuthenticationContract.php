<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Application\DTOs\AuthenticatedUserData;

interface JwtAuthenticationContract
{
    public function login(
        string $email,
        string $password
    ): AuthenticatedUserData;

    public function logout(): void;

    public function user(): ?User;
}
