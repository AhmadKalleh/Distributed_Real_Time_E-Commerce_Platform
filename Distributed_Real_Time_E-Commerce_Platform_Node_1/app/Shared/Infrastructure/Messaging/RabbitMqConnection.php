<?php

namespace App\Shared\Infrastructure\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMqConnection
{
    public static function open(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost'),
            false,
            'AMQPLAIN',
            null,
            'en_US',
            10.0,   // connection_timeout
            130.0,  // read_write_timeout
            null,
            false,   // keepalive
            60      // heartbeat
        );
    }
}
