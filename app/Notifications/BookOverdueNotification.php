<?php

namespace App\Notifications;

use App\Notifications\Concerns\BuildsLibraryNotificationPayload;
use App\Support\Notifications\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookOverdueNotification extends Notification implements ShouldQueue
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
        public NotificationType|string $kind = NotificationType::LOAN_OVERDUE,
    ) {
        $this->afterCommit();
    }
}
