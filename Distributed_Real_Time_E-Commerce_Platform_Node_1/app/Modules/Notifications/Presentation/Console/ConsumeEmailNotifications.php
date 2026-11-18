<?php

namespace App\Modules\Notifications\Presentation\Console;

use App\Modules\Notifications\Application\UseCases\DeliverEmailNotificationUseCase;
use App\Shared\Contracts\MessageConsumer;
use Illuminate\Console\Command;

/** The worker: runs forever, in its own container, and sends the emails published to RabbitMQ. */
final class ConsumeEmailNotifications extends Command
{
    protected $signature = 'notifications:consume-emails';

    protected $description = 'Worker: sends the email notifications published to RabbitMQ';

    public function handle(MessageConsumer $consumer, DeliverEmailNotificationUseCase $deliver): int
    {
        $this->info('Worker started. Waiting for email messages...');

        $consumer->consume(
            DeliverEmailNotificationUseCase::TOPIC,
            fn (array $payload) => $deliver->execute($payload),
        );

        return self::SUCCESS;
    }
}
