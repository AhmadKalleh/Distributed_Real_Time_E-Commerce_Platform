<?php

namespace App\Modules\Users\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Issues and validates stateless HS256 access tokens.
 *
 * Revocation (logout) is handled with a short-lived deny-list keyed by the
 * token's `jti`, so a revoked token stops working before it expires.
 */
class JwtService
{
    private const DENYLIST_PREFIX = 'jwt:revoked:';

    /**
     * @return array{token: string, expires_at: int}
     */
    public function issue(User $user): array
    {
        $now = now()->getTimestamp();
        $expiresAt = $now + config('jwt.ttl') * 60;

        $claims = [
            'iss' => config('jwt.issuer'),
            'sub' => (string) $user->getKey(),
            'role' => $user->role->value,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => (string) Str::uuid(),
        ];

        return [
            'token' => JWT::encode($claims, $this->secret(), config('jwt.algo')),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Returns the verified claims, or null when the token is malformed,
     * badly signed, expired, issued by someone else or revoked.
     */
    public function decode(string $token): ?object
    {
        JWT::$leeway = config('jwt.leeway');
        // Use the application clock (Carbon) so exp/nbf checks match the issue time.
        JWT::$timestamp = now()->getTimestamp();

        try {
            $claims = JWT::decode($token, new Key($this->secret(), config('jwt.algo')));
        } catch (Throwable) {
            return null;
        }

        if (($claims->iss ?? null) !== config('jwt.issuer') || empty($claims->sub) || empty($claims->jti)) {
            return null;
        }

        if (Cache::has(self::DENYLIST_PREFIX.$claims->jti)) {
            return null;
        }

        return $claims;
    }

    public function revoke(object $claims): void
    {
        $ttl = max(1, (int) $claims->exp - now()->getTimestamp());

        Cache::put(self::DENYLIST_PREFIX.$claims->jti, true, $ttl);
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be set and at least 32 characters long.');
        }

        return $secret;
    }
}
