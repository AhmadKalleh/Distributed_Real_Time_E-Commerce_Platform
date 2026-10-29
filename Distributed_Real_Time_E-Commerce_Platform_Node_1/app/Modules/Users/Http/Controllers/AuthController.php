<?php

namespace App\Modules\Users\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Users\Http\Requests\LoginRequest;
use App\Modules\Users\Http\Requests\RegisterRequest;
use App\Modules\Users\Http\Resources\UserResource;
use App\Modules\Users\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Compared against when the email does not exist, so a failed login
     * takes the same time whether or not the account exists.
     */
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        // Self-registration always creates a Customer; `role` is never taken from input.
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:'.Str::lower($request->input('email')).'|'.$request->ip();
        $maxAttempts = config('security.login.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ApiException(
                429,
                'TOO_MANY_LOGIN_ATTEMPTS',
                "Too many failed login attempts. Try again in {$retryAfter} seconds.",
                ['retry_after_seconds' => $retryAfter],
                ['Retry-After' => $retryAfter],
            );
        }

        $user = User::where('email', $request->input('email'))->first();
        $passwordOk = Hash::check($request->input('password'), $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($key, config('security.login.lockout_seconds'));

            throw new ApiException(401, 'INVALID_CREDENTIALS', 'The email or password is incorrect.', [
                'attempts_left' => RateLimiter::remaining($key, $maxAttempts),
            ]);
        }

        RateLimiter::clear($key);

        return $this->tokenResponse($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->jwt->revoke($request->attributes->get('jwt_claims'));

        return response()->json(null, 204);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = $this->jwt->issue($user);

        return response()->json([
            'data' => [
                'access_token' => $token['token'],
                'token_type' => 'Bearer',
                'expires_in' => $token['expires_at'] - now()->getTimestamp(),
                'user' => new UserResource($user),
            ],
        ], $status);
    }
}
