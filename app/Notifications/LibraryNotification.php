<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsLibraryNotificationPayload;
use App\Support\Notifications\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LibraryNotification extends Notification implements ShouldQueue
{
    use BuildsLibraryNotificationPayload;
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public NotificationType|string $kind,
        public string $title,
        public string $message,
        public string $url = '',
        public array $metadata = [],
        public ?string $relatedType = null,
        public int|string|null $relatedId = null,
    ) {
        $this->afterCommit();
    }
}
