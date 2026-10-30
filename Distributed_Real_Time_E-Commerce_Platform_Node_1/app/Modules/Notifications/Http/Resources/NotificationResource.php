<?php

namespace App\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Notifications\Models\UserNotification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            // Not called "data": a top-level "data" key stops Laravel wrapping the resource.
            'payload' => $this->data,
            'read_at' => $this->read_at?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
