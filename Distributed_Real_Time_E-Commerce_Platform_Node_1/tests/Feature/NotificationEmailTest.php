<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Mail\UserNotificationMail;
use App\Modules\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_events_email_the_customer_and_the_responsible(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['email' => 'buyer@example.test']);
        $admin = User::factory()->responsible()->create(['email' => 'admin@example.test']);
        $product = $this->makeProduct();

        $orderId = $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->authHeaders($customer))
            ->assertCreated()
            ->json('data.id');

        Mail::assertQueued(UserNotificationMail::class, fn ($mail) => $mail->hasTo('buyer@example.test')
            && $mail->notification->title === "Order #{$orderId} received");
        Mail::assertQueued(UserNotificationMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));

        $this->patchJson("/api/v1/orders/{$orderId}/status", ['status' => 'Confirmed'], $this->authHeaders($admin))->assertOk();

        Mail::assertQueued(UserNotificationMail::class, fn ($mail) => $mail->hasTo('buyer@example.test')
            && $mail->notification->title === "Order #{$orderId} is now Confirmed");
        Mail::assertQueued(UserNotificationMail::class, 3);
    }

    public function test_email_renders_the_notification(): void
    {
        $customer = User::factory()->create();
        $notification = UserNotification::create([
            'user_id' => $customer->id,
            'type' => 'order.status_changed',
            'title' => 'Order #7 is now Shipped',
            'body' => 'Your order #7 changed from Confirmed to Shipped.',
            'data' => ['order_id' => 7],
        ]);

        $mail = new UserNotificationMail($notification);

        $mail->assertHasSubject('Order #7 is now Shipped');
        $mail->assertSeeInHtml('Your order #7 changed from Confirmed to Shipped.');
        $mail->assertSeeInText('Order reference: #7');
    }

    public function test_mail_failure_does_not_break_the_order_request(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
        $customer = User::factory()->create();
        $product = $this->makeProduct();

        $this->postJson('/api/v1/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]], $this->authHeaders($customer))
            ->assertCreated();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $customer->id, 'type' => 'order.placed']);
    }
}
