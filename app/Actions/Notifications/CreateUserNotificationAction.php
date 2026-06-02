<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\LibraryNotification;
use Illuminate\Notifications\DatabaseNotification;

class CreateUserNotificationAction
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $recipient,
        ?User $sender,
        string $type,
        string $title,
        string $message,
        array $metadata = [],
        ?string $relatedType = null,
        ?int $relatedId = null
    ): ?DatabaseNotification {
        $existing = null;

        if ($relatedType && $relatedId) {
            $existing = $recipient->notifications()
                ->where('type', $type)
                ->where('data->related_type', $relatedType)
                ->where('data->related_id', $relatedId)
                ->first();
        }

        $payload = [
            'kind' => $type,
            'title' => $title,
            'message' => $message,
            'url' => (string) ($metadata['url'] ?? route('notifications.index', absolute: false)),
            'created_at' => now()->toIso8601String(),
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
            title: $title,
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








