<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmChannel;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use App\Support\Notifications\NotificationUiConfig;
use Illuminate\Notifications\Messages\BroadcastMessage;

trait BuildsLibraryNotificationPayload
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $notificationId = (string) ($this->id ?? '');
        $kind = NotificationType::normalize($this->kind);
        $ui = NotificationUiConfig::for($kind);

        return [
            'kind' => $kind->value,
            'type' => $kind->value,
            'ui' => $ui,
            'category' => $ui['category'],
            'icon' => $ui['icon'],
            'color' => $ui['color'],
            'badge' => $ui['badge'],
            'priority' => $ui['priority'],
            'notification_id' => $notificationId,
            'title' => $this->title ?: $kind->defaultTitle(),
            'body' => $this->message,
            'message' => $this->message,
            'url' => $this->url,
            'deep_link' => $notificationId !== '' ? "libraryapp://notification/{$notificationId}" : '',
            'created_at' => now()->toIso8601String(),
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
            'metadata' => NotificationMetadataBuilder::compact($this->metadata),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (($this->metadata['database_stored'] ?? false) === true) {
            return ['broadcast', FcmChannel::class];
        }

        return ['database', 'broadcast', FcmChannel::class];
    }

    /**
     * Persist database notifications immediately; queue only real-time delivery.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'broadcast' => config('queue.default'),
            FcmChannel::class => 'sync',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return $this->payload();
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::normalize($this->kind)->value;
    }

    public function broadcastType(): string
    {
        return NotificationType::normalize($this->kind)->value;
    }
}
