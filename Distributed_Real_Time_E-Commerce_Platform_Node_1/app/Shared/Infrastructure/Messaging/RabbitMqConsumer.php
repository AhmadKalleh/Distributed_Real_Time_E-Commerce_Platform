<?php

namespace App\Shared\Infrastructure\Messaging;

use App\Shared\Contracts\MessageConsumer;
use Closure;
use Illuminate\Support\Facades\Log;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class RabbitMqConsumer implements MessageConsumer
{
    public function consume(string $topic, Closure $handler): void
    {
        $connection = RabbitMqConnection::open();
        $channel = $connection->channel();
        RabbitMqTopology::declare($channel, $topic);

        // One message at a time: a slow or failing message never hides behind a pile of unacked ones.
        $channel->basic_qos(0, 1, false);

        $channel->basic_consume(
            $topic, '', false, false, false, false,
            /** @var AMQPMessage $message */
            fn (AMQPMessage $message) => $this->process($channel, $message, $handler),
        );

        while (count($channel->callbacks)) {
            $channel->wait();
        }
    }

    /**
     * @param AMQPChannel $channel
     * @param AMQPMessage $message
     * @param Closure $handler
     */

    private function process(AMQPChannel $channel, AMQPMessage $message, Closure $handler): void
    {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            // Unreadable message: retrying cannot help, so it goes straight to the dead-letter queue.
            Log::error('RabbitMQ: unreadable message sent to the dead-letter queue.', [
                'error' => $exception->getMessage(),
            ]);
            $channel->basic_reject($message->delivery_info['delivery_tag'], false);

            return;
        }

        try {
            $handler($payload);
            $channel->basic_ack($message->delivery_info['delivery_tag']); // acknowledged only after the work really succeeded
        } catch (Throwable $exception) {
            $this->handleFailure($channel, $message, $exception);
        }
    }

    private function handleFailure(AMQPChannel $channel, AMQPMessage $message, Throwable $exception): void
    {
        $attempts = $this->failedAttempts($message) + 1;
        $context = ['attempt' => $attempts, 'error' => $exception->getMessage()];

        if ($attempts >= config('rabbitmq.max_attempts')) {
            Log::error('RabbitMQ: message failed for good, sent to the dead-letter queue.', $context);
            $channel->basic_reject($message->delivery_info['delivery_tag'], false); // the queue's dead-letter exchange moves it to the DLQ

            return;
        }

        Log::warning('RabbitMQ: message failed, it will be retried.', $context);
        sleep(config('rabbitmq.retry_delay') * $attempts);

        // Republish first, then ack the original: a crash in between duplicates, never loses.
        $channel->basic_publish(
            RabbitMqPublisher::newMessage($message->getBody(), $attempts),
            RabbitMqTopology::EXCHANGE,
            $message->delivery_info['routing_key'],
        );
        $channel->basic_ack($message->delivery_info['delivery_tag']);
    }

    private function failedAttempts(AMQPMessage $message): int
    {
        if (! $message->has('application_headers')) {
            return 0;
        }

        return (int) ($message->get('application_headers')->getNativeData()['attempts'] ?? 0);
    }
}
