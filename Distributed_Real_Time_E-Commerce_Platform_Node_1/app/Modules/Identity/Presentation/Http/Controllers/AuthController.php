<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Modules\Identity\Application\UseCases\LoginUseCase;
use App\Modules\Identity\Application\UseCases\LogoutUseCase;
use App\Modules\Identity\Presentation\Http\Requests\LoginRequest;
use App\Modules\Identity\Presentation\Http\Resources\AuthenticatedUserResource;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthController
{
    public function login(
        LoginRequest $request,
        LoginUseCase $loginUseCase
    ): JsonResponse {
        $result = $loginUseCase->execute(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return ApiResponse::success(
            data: [
                'access_token' => $result->accessToken,
                'token_type' => $result->tokenType,
                'expires_in' => $result->expiresIn,
                'user' => (new AuthenticatedUserResource($result->user))
                    ->resolve($request),
            ],
            message: 'Login successful.',
            code: 200,
        );
    }

    public function logout(
        Request $request,
        LogoutUseCase $logoutUseCase
    ): JsonResponse {
        $logoutUseCase->execute();

        return ApiResponse::success(
            data: null,
            message: 'Logout successful.',
            code: 200,
        );
    }
}
