<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsLibraryNotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookDueSoonNotification extends Notification implements ShouldQueue
{
    use BuildsLibraryNotificationPayload;
    use Queueable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $url = '',
        public array $metadata = [],
        public ?string $relatedType = null,
        public int|string|null $relatedId = null,
        public string $kind = 'book_due_soon',
    ) {
        $this->afterCommit();
    }
}
