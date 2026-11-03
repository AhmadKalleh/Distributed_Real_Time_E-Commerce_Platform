<?php

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Contracts\JwtAuthenticationContract;
use App\Modules\Identity\Application\DTOs\AuthenticatedUserData;

final class LoginUseCase
{
    public function __construct(
        private readonly JwtAuthenticationContract $authentication
    ) {
    }

    public function execute(
        string $email,
        string $password
    ): AuthenticatedUserData {
        return $this->authentication->login($email, $password);
    }
}
