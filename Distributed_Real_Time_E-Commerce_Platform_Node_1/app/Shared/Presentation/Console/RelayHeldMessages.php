<?php

namespace App\Shared\Presentation\Console;

use App\Shared\Infrastructure\Messaging\RabbitMqPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

/**
 * Re-publishes messages that were held in MySQL while RabbitMQ was unavailable.
 *
 * It uses RabbitMqPublisher directly (not the holding decorator): if the broker is still
 * down, the message simply stays "pending" instead of being held a second time.
 */
final class RelayHeldMessages extends Command
{
    protected $signature = 'messages:relay-held
                            {--once : Process one batch and exit (useful for testing)}
                            {--interval=5 : Seconds to wait between batches}
                            {--batch=50 : Maximum messages per batch}';

    protected $description = 'Re-publish messages held while RabbitMQ was unavailable.';

    public function handle(RabbitMqPublisher $publisher): int
    {
        $this->info('Relay started.');

        do {
            try {
                $this->relayBatch($publisher, max(1, (int) $this->option('batch')));
            } catch (Throwable $exception) {
                // e.g. a database hiccup: report it and try again on the next round.
                report($exception);
                $this->error('Relay round failed: '.$exception->getMessage());
            }

            if ($this->option('once')) {
                break;
            }

            sleep(max(1, (int) $this->option('interval')));
        } while (true);

        return self::SUCCESS;
    }

    private function relayBatch(RabbitMqPublisher $publisher, int $limit): void
    {
        DB::transaction(function () use ($publisher, $limit) {
            // SKIP LOCKED lets several relays run side by side without sending a row twice.
            $rows = DB::table('held_messages')
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($rows as $row) {
                try {
                    $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    // A corrupt row must not block the rows behind it.
                    DB::table('held_messages')->where('id', $row->id)->update([
                        'status' => 'failed',
                        'last_error' => 'Invalid JSON payload: '.$exception->getMessage(),
                        'updated_at' => now(),
                    ]);
                    $this->error("Message #{$row->id} has an invalid payload; marked as failed.");

                    continue;
                }

                try {
                    $publisher->publish($row->topic, $payload);
                } catch (Throwable $exception) {
                    DB::table('held_messages')->where('id', $row->id)->update([
                        'attempts' => $row->attempts + 1,
                        'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                        'updated_at' => now(),
                    ]);
                    $this->warn("Broker still unavailable; message #{$row->id} stays pending.");

                    // Stop this batch: keeps the order and avoids hammering a broker that is down.
                    return;
                }

                DB::table('held_messages')->where('id', $row->id)->update([
                    'status' => 'published',
                    'attempts' => $row->attempts + 1,
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("Relayed message #{$row->id} to {$row->topic}.");
            }
        });
    }
}
