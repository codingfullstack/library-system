<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmChannel;
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
        $ui = NotificationUiConfig::for($this->kind);

        return [
            'kind' => $this->kind,
            'type' => $ui['type'],
            'ui' => $ui,
            'category' => $ui['category'],
            'icon' => $ui['icon'],
            'color' => $ui['color'],
            'notification_id' => $notificationId,
            'title' => $this->title,
            'body' => $this->message,
            'message' => $this->message,
            'url' => $this->url,
            'deep_link' => $notificationId !== '' ? "libraryapp://notification/{$notificationId}" : '',
            'created_at' => now()->toIso8601String(),
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
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
        return $this->kind;
    }

    public function broadcastType(): string
    {
        return $this->kind;
    }
}
