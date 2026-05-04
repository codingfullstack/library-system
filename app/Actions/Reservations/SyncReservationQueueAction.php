<?php

namespace App\Actions\Reservations;

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Reservation;
use Illuminate\Support\Collection;

class SyncReservationQueueAction
{
    private const DEFAULT_WINDOW_DAYS = 14;

    public function handle(int $libraryId, int $bookId): void
    {
        $this->expireElapsedReservations($libraryId, $bookId);

        $pendingReservations = $this->pendingReservations($libraryId, $bookId);

        if ($pendingReservations->isEmpty()) {
            return;
        }

        $firstReservation = $pendingReservations->shift();

        if ($firstReservation && ($firstReservation->expires_at === null || $firstReservation->expires_at->isPast())) {
            $firstReservation->update([
                'expires_at' => now()->addDays(self::DEFAULT_WINDOW_DAYS),
            ]);

            app(CreateUserNotificationAction::class)->handle(
                $firstReservation->user()->firstOrFail(),
                null,
                'reservation_ready',
                'Rezervacija paruosta',
                sprintf(
                    'Knyga "%s" jau laukia jusu. Atsiimkite iki %s.',
                    $firstReservation->book?->title ?: 'nezinoma knyga',
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

        foreach ($pendingReservations as $reservation) {
            if ($reservation->expires_at !== null) {
                $reservation->update([
                    'expires_at' => null,
                ]);
            }
        }
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
            ->get()
            ->each(function (Reservation $reservation) {
                $reservation->update([
                    'status' => Reservation::STATUS_EXPIRED,
                ]);
            });
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function pendingReservations(int $libraryId, int $bookId): Collection
    {
        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->pending()
            ->with(['user:id,name,email', 'book:id,title'])
            ->orderBy('reserved_at')
            ->get();
    }
}
