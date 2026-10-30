<?php

namespace App\Modules\Identity\Infrastructure\Authentication;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Identity\Application\Contracts\JwtAuthenticationContract;
use App\Modules\Identity\Application\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Application\DTOs\AuthenticatedUserData;
use App\Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

final class JwtAuthenticationService implements JwtAuthenticationContract
{
    public function __construct(
        private readonly UserRepositoryInterface $users
    ) {
    }

    public function login(
        string $email,
        string $password
    ): AuthenticatedUserData {
        $user = $this->users->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException();
        }

        $token = JWTAuth::fromUser($user);

        return new AuthenticatedUserData(
            accessToken: $token,
            tokenType: 'Bearer',
            expiresIn: (int) config('jwt.ttl', 60) * 60,
            user: $user,
        );
    }

    public function logout(): void
    {
        $token = JWTAuth::getToken();

        if (! $token) {
            throw new \Tymon\JWTAuth\Exceptions\TokenInvalidException(
                'Token is missing.'
            );
        }

        JWTAuth::invalidate($token);
    }

    public function user(): ?User
    {
        $user = auth('api')->user();

        return $user instanceof User ? $user : null;
    }
}
