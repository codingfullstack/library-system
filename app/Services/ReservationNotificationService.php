<?php

namespace App\Services;

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use App\Notifications\LibraryNotification;
use App\Support\Notifications\NotificationMessageBuilder;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\Log;

class ReservationNotificationService
{
    private const POSITION_TYPES = [
        NotificationType::RESERVATION_CREATED->value,
        NotificationType::RESERVATION_QUEUE_CHANGED->value,
        NotificationType::RESERVATION_READY->value,
    ];

    public function __construct(
        private readonly ReservationQueueService $queueService,
    ) {}

    public function notifyCreated(Reservation $reservation): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title', 'branch:id,name']);

        if (! $reservation->user) {
            return;
        }

        $position = $this->queueService->positionFor($reservation);
        $dueAt = $this->queueService->activeLoanDueAt($reservation->library_id, $reservation->book_id);
        $isFirst = $position === 1;

        app(CreateUserNotificationAction::class)->handle(
            $reservation->user,
            null,
            NotificationType::RESERVATION_CREATED,
            null,
            NotificationMessageBuilder::reservationCreated($reservation, $position, $dueAt, $isFirst),
            NotificationMetadataBuilder::reservation($reservation, [
                'queue_position' => $position,
                'new_queue_position' => $position,
                'due_at' => $dueAt,
                'is_first_in_queue' => $isFirst,
            ]),
            Reservation::class,
            $reservation->id
        );
    }

    public function notifyQueuePositionChanged(Reservation $reservation): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title', 'pickupBranch:id,name']);

        if (! $reservation->user) {
            return;
        }

        $position = $this->queueService->positionFor($reservation);

        if ($position === null || $position < 1) {
            return;
        }

        $oldPosition = $this->latestKnownPosition($reservation);

        if ($oldPosition === null || $oldPosition === $position) {
            return;
        }

        $dueAt = $this->queueService->activeLoanDueAt($reservation->library_id, $reservation->book_id);

        if ($this->hasDuplicateQueueChange($reservation, $oldPosition, $position, $dueAt)) {
            return;
        }

        $reservation->user->notify(new LibraryNotification(
            kind: NotificationType::RESERVATION_QUEUE_CHANGED,
            title: NotificationType::RESERVATION_QUEUE_CHANGED->defaultTitle(),
            message: NotificationMessageBuilder::reservationQueueChanged($reservation, $position, $dueAt),
            url: route('notifications.index', absolute: false),
            metadata: NotificationMetadataBuilder::reservation($reservation, [
                'old_queue_position' => $oldPosition,
                'new_queue_position' => $position,
                'queue_position' => $position,
                'due_at' => $dueAt,
                'is_first_in_queue' => $position === 1,
            ]),
            relatedType: Reservation::class,
            relatedId: $reservation->id,
        ));
    }

    /**
     * @param  array<int, int>  $oldPositions
     */
    public function notifyQueuePositionsChangedFromSnapshot(int $libraryId, int $bookId, array $oldPositions): void
    {
        $newPositions = $this->queueService->getPositionsForBook($libraryId, $bookId);
        $debugService = app(ReservationQueueDebugService::class);
        $notifications = [];

        $debugService->logSnapshot('before_queue_notification', $libraryId, $bookId, [
            'old_positions' => $oldPositions,
            'new_positions' => $newPositions,
        ]);

        $this->queueService
            ->pendingReservations($libraryId, $bookId)
            ->each(function (Reservation $reservation) use ($oldPositions, $newPositions, &$notifications): void {
                $oldPosition = $oldPositions[(int) $reservation->id] ?? null;
                $newPosition = $newPositions[(int) $reservation->id] ?? null;

                if ($oldPosition === null || $newPosition === null || $oldPosition === $newPosition) {
                    return;
                }

                $this->notifyQueuePositionChangedTo($reservation, $oldPosition, $newPosition);

                $notifications[] = [
                    'reservation_id' => (int) $reservation->id,
                    'old_position' => $oldPosition,
                    'new_position' => $newPosition,
                ];
            });

        $debugService->rememberQueueChange(
            $libraryId,
            $bookId,
            'queue_positions_changed',
            $oldPositions,
            $newPositions,
            $notifications
        );

        $debugService->logSnapshot('after_queue_notification', $libraryId, $bookId, [
            'old_positions' => $oldPositions,
            'new_positions' => $newPositions,
            'notifications' => $notifications,
        ]);
    }

    public function notifyReady(Reservation $reservation): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title', 'pickupBranch:id,name']);

        if (! $reservation->user) {
            return;
        }

        if (! $reservation->isReady()) {
            Log::warning('Attempted to send ready notification for non-first reservation', [
                'reservation_id' => $reservation->id,
                'status' => $reservation->status,
                'library_id' => $reservation->library_id,
                'book_id' => $reservation->book_id,
            ]);

            return;
        }

        app(CreateUserNotificationAction::class)->handle(
            $reservation->user,
            null,
            NotificationType::RESERVATION_READY,
            null,
            NotificationMessageBuilder::reservationReady($reservation),
            NotificationMetadataBuilder::reservation($reservation, [
                'is_first_in_queue' => true,
            ]),
            Reservation::class,
            $reservation->id
        );
    }

    private function latestKnownPosition(Reservation $reservation): ?int
    {
        $notifications = $reservation->user
            ->notifications()
            ->whereIn('type', self::POSITION_TYPES)
            ->where('data->related_type', Reservation::class)
            ->where('data->related_id', $reservation->id)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($notifications as $notification) {
            $metadata = $notification->data['metadata'] ?? [];
            $position = $metadata['new_queue_position'] ?? $metadata['new_position'] ?? $metadata['queue_position'] ?? null;

            if ($position !== null) {
                return (int) $position;
            }
        }

        return null;
    }

    private function hasDuplicateQueueChange(Reservation $reservation, int $oldPosition, int $newPosition, ?string $dueAt): bool
    {
        $query = $reservation->user
            ->notifications()
            ->where('type', NotificationType::RESERVATION_QUEUE_CHANGED->value)
            ->where('data->related_type', Reservation::class)
            ->where('data->related_id', $reservation->id)
            ->where('data->metadata->old_queue_position', $oldPosition)
            ->where('data->metadata->new_queue_position', $newPosition);

        $dueAt === null
            ? $query->whereNull('data->metadata->due_at')
            : $query->where('data->metadata->due_at', $dueAt);

        return $query->exists();
    }

    private function notifyQueuePositionChangedTo(Reservation $reservation, int $oldPosition, int $newPosition): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title']);

        if (! $reservation->user) {
            return;
        }

        $dueAt = $this->queueService->activeLoanDueAt($reservation->library_id, $reservation->book_id);

        if ($this->hasDuplicateQueueChange($reservation, $oldPosition, $newPosition, $dueAt)) {
            return;
        }

        $reservation->user->notify(new LibraryNotification(
            kind: NotificationType::RESERVATION_QUEUE_CHANGED,
            title: NotificationType::RESERVATION_QUEUE_CHANGED->defaultTitle(),
            message: NotificationMessageBuilder::reservationQueueChanged($reservation, $newPosition, $dueAt),
            url: route('notifications.index', absolute: false),
            metadata: NotificationMetadataBuilder::reservation($reservation, [
                'old_queue_position' => $oldPosition,
                'new_queue_position' => $newPosition,
                'queue_position' => $newPosition,
                'due_at' => $dueAt,
                'is_first_in_queue' => $newPosition === 1,
            ]),
            relatedType: Reservation::class,
            relatedId: $reservation->id,
        ));
    }
}
