<?php

namespace App\Shared\Contracts;

use Closure;

interface MessageConsumer
{
    /**
     * Blocks and handles the messages of a topic, one at a time.
     * A message is acknowledged only after $handler returns without throwing.
     *
     * @param Closure(array<string, mixed>): void $handler
     */
    public function consume(string $topic, Closure $handler): void;
}
