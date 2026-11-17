<?php

namespace App\Modules\Notifications\Infrastructure\Mail;

use App\Modules\Identity\Domain\Entities\User;
use App\Modules\Notifications\Application\Contracts\NotificationMailGatewayInterface;
use App\Modules\Notifications\Application\DTOs\Notifications\EmailMessageDTO;
use App\Modules\Notifications\Domain\Entities\UserNotification;
use Illuminate\Support\Facades\Mail;

/** Sends through the SMTP settings in .env (MAIL_*). Errors are not caught: the worker retries them. */
final class SmtpNotificationMailGateway implements NotificationMailGatewayInterface
{
    public function send(EmailMessageDTO $email): void
    {
        // Rebuilt in memory from the message (nothing is saved), so the existing mail
        // templates keep working and the worker never depends on the database.
        $notification = (new UserNotification())->forceFill([
            'id' => $email->notificationId,
            'type' => $email->type,
            'title' => $email->title,
            'body' => $email->body,
            'data' => $email->data,
            'created_at' => $email->createdAt,
        ]);
        $notification->setRelation('user', (new User())->forceFill([
            'name' => $email->toName,
            'email' => $email->toEmail,
        ]));

        Mail::to($email->toEmail, $email->toName)->send(new UserNotificationMail($notification));
    }
}
