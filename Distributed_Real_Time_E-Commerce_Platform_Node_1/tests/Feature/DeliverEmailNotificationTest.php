<?php

namespace Tests\Feature;

use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\UseCases\DeliverEmailNotificationUseCase;
use App\Modules\Notifications\Infrastructure\Persistence\EloquentNotificationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DeliverEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_messages_send_once_and_record_the_delivery(): void
    {
        $mail = Mockery::mock(NotificationMailGatewayInterface::class);
        $mail->shouldReceive('send')->once();
        $deliver = new DeliverEmailNotificationUseCase($mail, new EloquentNotificationRepository());
        $payload = $this->emailPayload(4821);

        $deliver->execute($payload);
        $deliver->execute($payload);

        $this->assertDatabaseHas('notification_email_deliveries', [
            'notification_id' => '4821',
        ]);
        $this->assertNotNull(DB::table('notification_email_deliveries')
            ->where('notification_id', '4821')
            ->value('sent_at'));
    }

    public function test_failed_email_releases_its_reservation_for_a_retry(): void
    {
        $attempts = 0;
        $mail = Mockery::mock(NotificationMailGatewayInterface::class);
        $mail->shouldReceive('send')
            ->twice()
            ->andReturnUsing(function () use (&$attempts): void {
                if (++$attempts === 1) {
                    throw new RuntimeException('SMTP unavailable');
                }
            });
        $deliver = new DeliverEmailNotificationUseCase($mail, new EloquentNotificationRepository());
        $payload = $this->emailPayload(4822);

        try {
            $deliver->execute($payload);
            $this->fail('The failed email should be retried by RabbitMQ.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertDatabaseMissing('notification_email_deliveries', [
            'notification_id' => '4822',
        ]);

        $deliver->execute($payload);

        $this->assertNotNull(DB::table('notification_email_deliveries')
            ->where('notification_id', '4822')
            ->value('sent_at'));
    }

    /**
     * @return array<string, mixed>
     */
    private function emailPayload(int $notificationId): array
    {
        return [
            'notification_id' => $notificationId,
            'to_email' => 'customer@example.test',
            'to_name' => 'Customer',
            'type' => 'order.placed',
            'title' => 'Order received',
            'body' => 'Your order was received.',
        ];
    }
}
