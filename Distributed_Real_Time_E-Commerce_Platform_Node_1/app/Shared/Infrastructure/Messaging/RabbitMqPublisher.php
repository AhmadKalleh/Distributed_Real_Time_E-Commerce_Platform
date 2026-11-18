<?php

namespace App\Shared\Infrastructure\Messaging;

use App\Shared\Contracts\MessagePublisher;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;

final class RabbitMqPublisher implements MessagePublisher
{
    public function publish(string $topic, array $payload): void
    {
        $connection = RabbitMqConnection::open();

        try {
            $channel = $connection->channel();
            RabbitMqTopology::declare($channel, $topic);

            // Publisher confirms: we return only once the broker has safely accepted the message.
            $channel->confirm_select();
            $channel->set_nack_handler(fn () => throw new RuntimeException('RabbitMQ refused the message.'));

            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $channel->basic_publish(self::newMessage($body), RabbitMqTopology::EXCHANGE, $topic);
            $channel->wait_for_pending_acks(5);

            $channel->close();
        } finally {
            $connection->close();
        }
    }

    /** Persistent message carrying the number of failed attempts so far. */
    public static function newMessage(string $body, int $attempts = 0): AMQPMessage
    {
        return new AMQPMessage($body, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable(['attempts' => $attempts]),
        ]);
    }
}
