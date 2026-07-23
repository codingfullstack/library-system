<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\LibraryNotification;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use App\Support\Notifications\NotificationUiConfig;
use Illuminate\Notifications\DatabaseNotification;

class CreateUserNotificationAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $recipient,
        ?User $sender,
        NotificationType|string $type,
        ?string $title,
        string $message,
        array $metadata = [],
        ?string $relatedType = null,
        ?int $relatedId = null
    ): ?DatabaseNotification {
        $type = NotificationType::normalize($type);
        $ui = NotificationUiConfig::for($type);
        $metadata = NotificationMetadataBuilder::compact($metadata);
        $existing = null;

        if ($relatedType && $relatedId) {
            $existing = $recipient->notifications()
                ->where('type', $type->value)
                ->where('data->related_type', $relatedType)
                ->where('data->related_id', $relatedId)
                ->first();
        }

        $payload = [
            'kind' => $type->value,
            'type' => $type->value,
            'title' => $title ?: $type->defaultTitle(),
            'message' => $message,
            'url' => (string) ($metadata['url'] ?? route('notifications.index', absolute: false)),
            'created_at' => now()->toIso8601String(),
            'ui' => $ui,
            'category' => $ui['category'],
            'icon' => $ui['icon'],
            'color' => $ui['color'],
            'badge' => $ui['badge'],
            'priority' => $ui['priority'],
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'metadata' => $metadata,
        ];

        if ($existing) {
            $existing->forceFill([
                'data' => array_merge($payload, [
                    'sender' => $sender ? [
                        'id' => $sender->id,
                        'name' => $sender->name,
                        'email' => $sender->email,
                    ] : null,
                ]),
            ]);
            $existing->save();

            return $existing;
        }

        $recipient->notify(new LibraryNotification(
            kind: $type,
            title: $title ?: $type->defaultTitle(),
            message: $message,
            url: $payload['url'],
            metadata: array_merge($metadata, [
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'email' => $sender->email,
                ] : null,
            ]),
            relatedType: $relatedType,
            relatedId: $relatedId,
        ));

        return null;
    }
}
