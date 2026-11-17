<?php

return [
    'host' => env('RABBITMQ_HOST', '127.0.0.1'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'ecommerce'),
    'password' => env('RABBITMQ_PASSWORD', 'ecommerce-local-pass'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    // Total delivery attempts per message before it goes to the dead-letter queue.
    'max_attempts' => (int) env('RABBITMQ_MAX_ATTEMPTS', 3),

    // Seconds to wait before retrying a failed message (multiplied by the attempt number).
    'retry_delay' => (int) env('RABBITMQ_RETRY_DELAY', 5),
];
