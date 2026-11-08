<?php

namespace App\Http\Controllers\Performance;

use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;
use JsonException;

final class OptimizedCursor
{
    public static function decode(?string $encoded): ?Cursor
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        try {
            $payload = $decoded === false
                ? null
                : json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $payload = null;
        }

        $id = is_array($payload) ? filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT) : false;
        $direction = is_array($payload) ? ($payload['_pointsToNextItems'] ?? null) : null;

        if ($id === false || $id < 1 || !is_bool($direction)) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        return new Cursor(['id' => $id], $direction);
    }
}
