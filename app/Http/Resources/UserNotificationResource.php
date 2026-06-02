<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        $metadata = $data['metadata'] ?? ($this->metadata ?? []);
        $sender = $data['sender'] ?? ($metadata['sender'] ?? null);

        return [
            'id' => $this->id,
            'type' => $data['kind'] ?? $this->type,
            'kind' => $data['kind'] ?? $this->type,
            'title' => $data['title'] ?? $this->title,
            'message' => $data['message'] ?? $this->message,
            'url' => $data['url'] ?? route('notifications.index', absolute: false),
            'metadata' => $metadata,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'sender' => $sender ?: ($this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'email' => $this->sender->email,
            ] : null),
        ];
    }
}








