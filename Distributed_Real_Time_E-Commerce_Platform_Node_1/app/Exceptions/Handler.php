<?php

namespace App\Exceptions;

use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Shared\Exceptions\BaseApiException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Fields that should never be flashed to the session.
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register exception handling callbacks.
     */
    public function register(): void
    {

    }

    /**
     * Render exceptions as a consistent JSON response for API requests.
     */
    public function render($request, Throwable $exception)
    {
        $isApiRequest = $request->is('api')
            || $request->is('api/*')
            || $request->expectsJson();

        if (! $isApiRequest) {
            return parent::render($request, $exception);
        }

        /*
         * 1. Validation errors
         */
        else if ($exception instanceof ValidationException) {
            return ApiResponse::error(
                message: 'The given data was invalid.',
                code: 422,
                data: $exception->errors()
            );
        }

        /*
         * 2. Authentication errors
         */
        else if ($exception instanceof AuthenticationException) {
            return ApiResponse::error(
                message: 'Unauthenticated.',
                code: 401
            );
        }

        /*
         * 3. Custom API exceptions
         */

        else if ($exception instanceof BaseApiException)
        {
            return ApiResponse::error(
                message: $exception->getMessage(),
                code: $exception->httpStatus
            );
        }

        /*
         * 4. Authorization errors
         */
        else if ($exception instanceof AuthorizationException) {
            return ApiResponse::error(
                message: 'You are not authorized to perform this action.',
                code: 403
            );
        }

        /*
         * 5. Eloquent model not found
         */
        else if ($exception instanceof ModelNotFoundException) {
            return ApiResponse::error(
                message: 'The requested resource was not found.',
                code: 404
            );
        }

        /*
         * 6. Rate limiting
         */
        else if ($exception instanceof ThrottleRequestsException) {
            return ApiResponse::error(
                message: 'Too many requests. Please try again later.',
                code: 429
            );
        }

        /*
         * 7. Database errors
         */
        else if ($exception instanceof QueryException) {
            return ApiResponse::error(
                message: config('app.debug')
                    ? $exception->getMessage()
                    : 'A database error occurred.',
                code: 500
            );
        }

        /*
         * 8. Other HTTP exceptions:
         * 404, 405, 419, 429, and other HTTP status codes.
         */
        else if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();

            $message = $exception->getMessage()
                ?: $this->getHttpErrorMessage($statusCode);

            return ApiResponse::error(
                message: $message,
                code: $statusCode
            );
        }

        /*
         * 9. Unexpected errors
         */
        return ApiResponse::error(
            message: config('app.debug')
                ? $exception->getMessage()
                : 'An unexpected server error occurred.',
            code: 500
        );
    }

    /**
     * Return a safe default message for common HTTP errors.
     */
    private function getHttpErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request.',
            401 => 'Unauthenticated.',
            403 => 'You are not authorized to perform this action.',
            404 => 'The requested resource was not found.',
            405 => 'The requested HTTP method is not allowed.',
            408 => 'The request timed out.',
            409 => 'The request conflicts with the current resource state.',
            419 => 'The request has expired.',
            422 => 'The given data was invalid.',
            429 => 'Too many requests. Please try again later.',
            500 => 'An unexpected server error occurred.',
            503 => 'The service is temporarily unavailable.',
            default => 'The request could not be completed.',
        };
    }
}
