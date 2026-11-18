<?php

namespace App\Shared\Infrastructure\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * For a topic "x" we get:
 *
 *   exchange ecommerce.events --x--> queue "x"
 *                                     | rejected / failed too many times
 *                                     v  (dead-letter exchange)
 *   exchange ecommerce.events.dlx --x--> queue "x.dlq"
 */
final class RabbitMqTopology
{
    public const EXCHANGE = 'ecommerce.events';
    public const DEAD_LETTER_EXCHANGE = 'ecommerce.events.dlx';

    public static function declare(AMQPChannel $channel, string $topic): void
    {
        // Dead-letter side 
        $channel->exchange_declare(self::DEAD_LETTER_EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::deadLetterQueue($topic), false, true, false, false);
        $channel->queue_bind(self::deadLetterQueue($topic), self::DEAD_LETTER_EXCHANGE, $topic);

        // Main side
        $channel->exchange_declare(self::EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare($topic, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
            'x-dead-letter-routing-key' => $topic,
        ]));
        $channel->queue_bind($topic, self::EXCHANGE, $topic);
    }

    public static function deadLetterQueue(string $topic): string
    {
        return $topic . '.dlq';
    }
}
