<?php

namespace App\Modules\Identity\Application\DTOs;

use App\Modules\Identity\Domain\Entities\User;

final class AuthenticatedUserData
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
        public readonly User $user,
    ) {
    }
}
