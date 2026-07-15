<?php

namespace App\Services;

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use App\Notifications\LibraryNotification;
use Illuminate\Support\Facades\Log;

class ReservationNotificationService
{
    private const POSITION_TYPES = [
        'reservation_created',
        'reservation_queue_changed',
        'reservation_ready',
    ];

    public function __construct(
        private readonly ReservationQueueService $queueService,
    ) {}

    public function notifyCreated(Reservation $reservation): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title']);

        if (! $reservation->user) {
            return;
        }

        $position = $this->queueService->positionFor($reservation);
        $dueAt = $this->queueService->activeLoanDueAt($reservation->library_id, $reservation->book_id);
        $isFirst = $position === 1;

        app(CreateUserNotificationAction::class)->handle(
            $reservation->user,
            null,
            'reservation_created',
            'Rezervacija sukurta',
            $this->createdMessage($reservation, $position, $dueAt, $isFirst),
            $this->metadata($reservation, [
                'queue_position' => $position,
                'new_position' => $position,
                'due_at' => $dueAt,
                'is_first_in_queue' => $isFirst,
            ]),
            Reservation::class,
            $reservation->id
        );
    }

    public function notifyQueuePositionChanged(Reservation $reservation): void
    {
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title']);

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
            kind: 'reservation_queue_changed',
            title: 'Rezervacijos eilė pasikeitė',
            message: $this->queueChangedMessage($reservation, $position, $dueAt),
            url: route('notifications.index', absolute: false),
            metadata: $this->metadata($reservation, [
                'old_position' => $oldPosition,
                'new_position' => $position,
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
        $reservation->loadMissing(['user:id,name,email', 'book:id,slug,title']);

        if (! $reservation->user) {
            return;
        }

        $position = $this->queueService->positionFor($reservation);

        if ($position === null || $position < 1) {
            return;
        }

        if ($position !== 1) {
            Log::warning('Attempted to send ready notification for non-first reservation', [
                'reservation_id' => $reservation->id,
                'position' => $position,
                'library_id' => $reservation->library_id,
                'book_id' => $reservation->book_id,
            ]);

            return;
        }

        app(CreateUserNotificationAction::class)->handle(
            $reservation->user,
            null,
            'reservation_ready',
            'Rezervacija paruošta',
            sprintf(
                'Knyga "%s" jau laukia jūsų. Atsiimkite iki %s.',
                $reservation->book?->title ?: 'nežinoma knyga',
                $reservation->expires_at?->format('Y-m-d H:i') ?: '-'
            ),
            $this->metadata($reservation, [
                'queue_position' => $position,
                'new_position' => $position,
                'due_at' => null,
                'pickup_expires_at' => $reservation->expires_at?->toDateTimeString(),
                'is_first_in_queue' => $position === 1,
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
            $position = $metadata['new_position'] ?? $metadata['queue_position'] ?? null;

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
            ->where('type', 'reservation_queue_changed')
            ->where('data->related_type', Reservation::class)
            ->where('data->related_id', $reservation->id)
            ->where('data->metadata->old_position', $oldPosition)
            ->where('data->metadata->new_position', $newPosition);

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
            kind: 'reservation_queue_changed',
            title: 'Rezervacijos eilė pasikeitė',
            message: $this->queueChangedMessage($reservation, $newPosition, $dueAt),
            url: route('notifications.index', absolute: false),
            metadata: $this->metadata($reservation, [
                'old_position' => $oldPosition,
                'new_position' => $newPosition,
                'queue_position' => $newPosition,
                'due_at' => $dueAt,
                'is_first_in_queue' => $newPosition === 1,
            ]),
            relatedType: Reservation::class,
            relatedId: $reservation->id,
        ));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function metadata(Reservation $reservation, array $extra): array
    {
        return array_merge([
            'reservation_id' => $reservation->id,
            'book_id' => $reservation->book_id,
            'book_title' => $reservation->book?->title,
            'library_id' => $reservation->library_id,
            'scope' => $reservation->scope,
            'branch_id' => $reservation->branch_id,
        ], $extra);
    }

    private function createdMessage(Reservation $reservation, ?int $position, ?string $dueAt, bool $isFirst): string
    {
        $message = sprintf(
            'Jūs sėkmingai rezervavote knygą "%s". Jūsų vieta eilėje: %s.',
            $reservation->book?->title ?: 'nežinoma knyga',
            $position ?: '-'
        );

        if ($dueAt) {
            return $message.' Šiuo metu knyga paskolinta kitam skaitytojui iki '.$dueAt.'.';
        }

        if ($isFirst) {
            return $message.' Esate pirmi eilėje, informuosime, kai knygą bus galima pasiimti.';
        }

        return $message.' Knyga šiuo metu nėra paskolinta su žinoma grąžinimo data.';
    }

    private function queueChangedMessage(Reservation $reservation, int $position, ?string $dueAt): string
    {
        $message = sprintf(
            'Jūsų rezervacijos eilė pasikeitė. Knyga "%s" - dabar esate %d vietoje eilėje.',
            $reservation->book?->title ?: 'nežinoma knyga',
            $position
        );

        if ($dueAt) {
            return $message.' Dabartinis skaitytojas turi grąžinti knygą iki '.$dueAt.'.';
        }

        if ($position === 1) {
            return $message.' Esate pirmi eilėje.';
        }

        return $message;
    }
}
