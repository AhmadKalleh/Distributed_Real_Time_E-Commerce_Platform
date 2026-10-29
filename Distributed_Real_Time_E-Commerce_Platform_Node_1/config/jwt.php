<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Settings
    |--------------------------------------------------------------------------
    |
    | Tokens are signed with HMAC-SHA256 using JWT_SECRET. Keep the secret
    | long (at least 32 random bytes) and never commit it.
    |
    */

    'secret' => env('JWT_SECRET'),

    'algo' => 'HS256',

    // Token lifetime in minutes.
    'ttl' => (int) env('JWT_TTL', 60),

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    // Allowed clock skew in seconds when validating exp / nbf / iat.
    'leeway' => 5,

];
