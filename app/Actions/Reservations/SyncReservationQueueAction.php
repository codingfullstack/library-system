<?php

namespace App\Actions\Reservations;

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\BookCopy;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;

class SyncReservationQueueAction
{
    private const DEFAULT_WINDOW_DAYS = 14;

    public function handle(int $libraryId, int $bookId): void
    {
        $this->expireElapsedReservations($libraryId, $bookId);

        $firstReservation = $this->firstPendingReservation($libraryId, $bookId);

        if (! $firstReservation) {
            return;
        }

        if (! $this->hasAvailableCopies($libraryId, $bookId)) {
            $this->pendingReservationsQuery($libraryId, $bookId)
                ->whereNotNull('expires_at')
                ->update(['expires_at' => null]);

            return;
        }

        if ($firstReservation && ($firstReservation->expires_at === null || $firstReservation->expires_at->isPast())) {
            $firstReservation->update([
                'expires_at' => now()->addDays(self::DEFAULT_WINDOW_DAYS),
            ]);

            app(CreateUserNotificationAction::class)->handle(
                $firstReservation->user,
                null,
                'reservation_ready',
                'Rezervacija paruošta',
                sprintf(
                    'Knyga "%s" jau laukia jūsų. Atsiimkite iki %s.',
                    $firstReservation->book?->title ?: 'nežinoma knyga',
                    $firstReservation->expires_at?->format('Y-m-d H:i') ?: '-'
                ),
                [
                    'reservation_id' => $firstReservation->id,
                    'book_id' => $firstReservation->book_id,
                    'book_title' => $firstReservation->book?->title,
                    'expires_at' => $firstReservation->expires_at?->toDateTimeString(),
                ],
                Reservation::class,
                $firstReservation->id
            );
        }

        $this->pendingReservationsQuery($libraryId, $bookId)
            ->whereKeyNot($firstReservation->id)
            ->whereNotNull('expires_at')
            ->update(['expires_at' => null]);
    }

    private function expireElapsedReservations(int $libraryId, int $bookId): void
    {
        Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('status', Reservation::STATUS_RESERVED)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => Reservation::STATUS_EXPIRED]);
    }

    private function hasAvailableCopies(int $libraryId, int $bookId): bool
    {
        return BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('status', BookCopy::STATUS_AVAILABLE)
            ->exists();
    }

    private function firstPendingReservation(int $libraryId, int $bookId): ?Reservation
    {
        return $this->pendingReservationsQuery($libraryId, $bookId)
            ->with(['user:id,name,email', 'book:id,slug,title'])
            ->first();
    }

    private function pendingReservationsQuery(int $libraryId, int $bookId): Builder
    {
        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->pending()
            ->orderBy('reserved_at');
    }
}
