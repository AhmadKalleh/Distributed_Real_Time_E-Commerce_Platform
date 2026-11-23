<?php

namespace App\Shared\Infrastructure\Messaging;

use App\Shared\Contracts\MessagePublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Store-and-forward decorator.
 *
 * If the broker is unreachable (or refuses the message), the message is held in MySQL
 * instead of being lost. The relay command publishes it later, once RabbitMQ is back.
 */
final readonly class HoldingMessagePublisher implements MessagePublisher
{
    public function __construct(private MessagePublisher $inner) {}

    public function publish(string $topic, array $payload): void
    {
        try {
            $this->inner->publish($topic, $payload);
        } catch (Throwable $exception) {
            DB::table('held_messages')->insert([
                'topic' => $topic,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::warning('RabbitMQ unavailable: message held for later delivery.', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
