<?php

namespace App\Shared\Contracts;

interface MessagePublisher
{
    /**
     * Publishes a durable message to the given topic.
     * Returns only after the broker has accepted it; throws otherwise.
     *
     * @param array<string, mixed> $payload
     */
    public function publish(string $topic, array $payload): void;
}
