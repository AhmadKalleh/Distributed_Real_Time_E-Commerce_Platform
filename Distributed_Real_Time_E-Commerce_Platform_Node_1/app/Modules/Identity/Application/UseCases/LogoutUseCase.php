<?php

namespace App\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\Contracts\JwtAuthenticationContract;

final class LogoutUseCase
{
    public function __construct(
        private readonly JwtAuthenticationContract $authentication
    ) {
    }

    public function execute(): void
    {
        $this->authentication->logout();
    }
}
