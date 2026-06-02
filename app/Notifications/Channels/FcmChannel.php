<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function send(object $notifiable, Notification $notification): array
    {
        if (! $notifiable instanceof User) {
            return [
                'sent' => 0,
                'failed' => 0,
                'responses' => [],
            ];
        }

        $payload = method_exists($notification, 'toFcm')
            ? $notification->toFcm($notifiable)
            : [];

        return $this->fcmService->sendToUser(
            $notifiable,
            (string) ($payload['title'] ?? ''),
            (string) ($payload['body'] ?? $payload['message'] ?? ''),
            $payload
        );
    }
}
