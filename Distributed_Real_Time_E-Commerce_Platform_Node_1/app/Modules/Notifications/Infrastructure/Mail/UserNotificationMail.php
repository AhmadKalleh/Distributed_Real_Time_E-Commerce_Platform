<?php

namespace App\Modules\Notifications\Infrastructure\Mail;

use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Email copy of an in-app notification.
 * Not queued by Laravel anymore: it is sent by the RabbitMQ worker.
 */
class UserNotificationMail extends Mailable
{
    public function __construct(public readonly UserNotification $notification)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notification->title);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            text: 'emails.notification-text',
        );
    }
}
