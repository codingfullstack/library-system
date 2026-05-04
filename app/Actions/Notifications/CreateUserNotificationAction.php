<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Models\UserNotification;

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
    ): UserNotification {
        $existing = null;

        if ($relatedType && $relatedId) {
            $existing = UserNotification::query()
                ->where('user_id', $recipient->id)
                ->where('type', $type)
                ->where('related_type', $relatedType)
                ->where('related_id', $relatedId)
                ->first();
        }

        if ($existing) {
            return tap($existing)->update([
                'sent_by' => $sender?->id,
                'title' => $title,
                'message' => $message,
                'metadata' => $metadata,
            ]);
        }

        return UserNotification::create([
            'user_id' => $recipient->id,
            'sent_by' => $sender?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'metadata' => $metadata,
        ]);
    }
}
