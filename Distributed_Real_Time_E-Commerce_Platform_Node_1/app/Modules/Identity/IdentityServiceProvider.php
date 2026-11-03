<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Contracts\JwtAuthenticationContract;
use App\Modules\Identity\Application\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Infrastructure\Authentication\JwtAuthenticationService;
use App\Modules\Identity\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class,EloquentUserRepository::class);

        $this->app->bind(JwtAuthenticationContract::class,JwtAuthenticationService::class);
    }

    public function boot(): void
    {
    }
}
