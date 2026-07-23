<?php

namespace App\Http\Resources;

use App\Support\Notifications\NotificationUiConfig;
use App\Support\Notifications\NotificationType;
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
        $kind = NotificationType::normalize($data['kind'] ?? $data['type'] ?? $this->type);
        $ui = NotificationUiConfig::for($kind);

        return [
            'id' => $this->id,
            'type' => $kind->value,
            'kind' => $kind->value,
            'ui' => $ui,
            'category' => $ui['category'],
            'icon' => $ui['icon'],
            'color' => $ui['color'],
            'badge' => $ui['badge'],
            'priority' => $ui['priority'],
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
