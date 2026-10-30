<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login Brute-Force Protection
    |--------------------------------------------------------------------------
    |
    | After `max_attempts` failed logins for the same email + IP, further
    | attempts are rejected with 429 until `lockout_seconds` have passed.
    | `per_ip_per_minute` caps all auth requests from a single IP.
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 300),
        'per_ip_per_minute' => (int) env('AUTH_PER_IP_PER_MINUTE', 20),
    ],

    'api_per_minute' => (int) env('API_PER_MINUTE', 120),

];
