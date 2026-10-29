<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        ApiException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Machine-readable codes for plain HTTP errors.
     */
    private const HTTP_CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        413 => 'PAYLOAD_TOO_LARGE',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        422 => 'VALIDATION_ERROR',
        429 => 'TOO_MANY_REQUESTS',
        500 => 'INTERNAL_ERROR',
        503 => 'SERVICE_UNAVAILABLE',
    ];

    public function register(): void
    {
        //
    }

    public function render($request, Throwable $e)
    {
        if ($request->is('api/*')) {
            return $this->renderApiError($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Every API error has the same shape:
     * { "error": { "status": 403, "code": "FORBIDDEN", "message": "...", "details": {...} } }
     */
    protected function renderApiError(Request $request, Throwable $e): Response
    {
        $e = $this->prepareException($this->mapException($e));

        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        return match (true) {
            $e instanceof ApiException => $this->errorResponse($e->status, $e->errorCode, $e->getMessage(), $e->details, $e->headers),

            $e instanceof AuthenticationException => $this->errorResponse(401, 'UNAUTHENTICATED', 'Authentication is required. Send a valid Bearer token.'),

            $e instanceof ValidationException => $this->errorResponse(422, 'VALIDATION_ERROR', 'The given data was invalid.', $e->errors()),

            $e instanceof HttpExceptionInterface => $this->errorResponse(
                $e->getStatusCode(),
                self::HTTP_CODES[$e->getStatusCode()] ?? 'HTTP_ERROR',
                $this->httpMessage($e),
                [],
                $e->getHeaders(),
            ),

            default => $this->errorResponse(
                500,
                'INTERNAL_ERROR',
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
            ),
        };
    }

    protected function httpMessage(HttpExceptionInterface $e): string
    {
        $status = $e->getStatusCode();

        return match ($status) {
            403 => 'You are not allowed to perform this action.',
            404 => 'The requested resource was not found.',
            405 => 'This HTTP method is not allowed for this endpoint.',
            429 => 'Too many requests. Please slow down.',
            default => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'HTTP error'),
        };
    }

    protected function errorResponse(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['status' => $status, 'code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
