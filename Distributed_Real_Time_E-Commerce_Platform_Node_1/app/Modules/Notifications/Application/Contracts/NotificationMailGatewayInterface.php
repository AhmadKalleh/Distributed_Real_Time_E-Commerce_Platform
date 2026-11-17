<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Modules\Notifications\Application\DTOs\Notifications\EmailMessageDTO;

interface NotificationMailGatewayInterface
{
    /** Sends the email right now (used by the worker, never by the API request). */
    public function send(EmailMessageDTO $email): void;
}
