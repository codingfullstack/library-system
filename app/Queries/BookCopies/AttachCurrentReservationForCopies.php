<?php

namespace App\Queries\BookCopies;

use App\Models\BookCopy;
use App\Models\Reservation;

class AttachCurrentReservationForCopies
{
    /**
     * @param  iterable<int, BookCopy>  $copies
     */
    public function handle(iterable $copies, bool|callable $canViewOperationalDetails): void
    {
        $copies = collect($copies)->values();

        if ($copies->isEmpty()) {
            return;
        }

        $eligibleCopies = $copies
            ->filter(fn (BookCopy $copy) => $copy->book_id && $copy->library_id)
            ->filter(fn (BookCopy $copy) => is_callable($canViewOperationalDetails)
                ? (bool) $canViewOperationalDetails($copy)
                : $canViewOperationalDetails)
            ->values();

        if ($eligibleCopies->isEmpty()) {
            return;
        }

        $copyIds = $eligibleCopies->pluck('id')->map(fn ($id) => (int) $id)->all();
        $contexts = $eligibleCopies
            ->map(fn (BookCopy $copy) => [(int) $copy->library_id, (int) $copy->book_id])
            ->unique(fn (array $context) => $context[0].':'.$context[1])
            ->values();

        $reservations = Reservation::query()
            ->with([
                'pickupBranch:id,name',
                'user:id,name,email,membership_number',
            ])
            ->where(function ($query) use ($contexts) {
                foreach ($contexts as [$libraryId, $bookId]) {
                    $query->orWhere(fn ($contextQuery) => $contextQuery
                        ->where('library_id', $libraryId)
                        ->where('book_id', $bookId));
                }
            })
            ->active()
            ->where(function ($query) use ($copyIds) {
                $query->where('status', Reservation::STATUS_WAITING)
                    ->orWhere(function ($readyQuery) use ($copyIds) {
                        $readyQuery
                            ->where('status', Reservation::STATUS_READY)
                            ->whereIn('assigned_book_copy_id', $copyIds);
                    });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $pendingByContext = $reservations
            ->filter(fn (Reservation $reservation) => $reservation->isPending())
            ->groupBy(fn (Reservation $reservation) => $this->contextKey((int) $reservation->library_id, (int) $reservation->book_id));

        $queueSizes = $pendingByContext->map->count();
        $queuePositions = [];

        foreach ($pendingByContext as $contextKey => $pendingReservations) {
            foreach ($pendingReservations->values() as $index => $reservation) {
                $queuePositions[$contextKey][(int) $reservation->id] = $index + 1;
            }
        }

        foreach ($eligibleCopies as $copy) {
            $contextKey = $this->contextKey((int) $copy->library_id, (int) $copy->book_id);
            $currentReservation = $reservations->first(fn (Reservation $reservation) => $this->reservationServesCopy($reservation, $copy));

            if ($currentReservation?->isPending()) {
                $currentReservation->setAttribute('queue_position', $queuePositions[$contextKey][(int) $currentReservation->id] ?? null);
                $currentReservation->setAttribute('queue_size', $queueSizes[$contextKey] ?? 0);
            }

            $copy->setAttribute('current_reservation', $currentReservation);
        }
    }

    private function reservationServesCopy(Reservation $reservation, BookCopy $copy): bool
    {
        if ((int) $reservation->library_id !== (int) $copy->library_id || (int) $reservation->book_id !== (int) $copy->book_id) {
            return false;
        }

        if ($reservation->isReady()) {
            return (int) $reservation->assigned_book_copy_id === (int) $copy->id;
        }

        if (! $reservation->isPending()) {
            return false;
        }

        if ($reservation->scope === Reservation::SCOPE_BRANCH) {
            return (int) $reservation->branch_id === (int) $copy->branch_id;
        }

        return ($reservation->scope ?: Reservation::SCOPE_LIBRARY) === Reservation::SCOPE_LIBRARY
            && $reservation->branch_id === null;
    }

    private function contextKey(int $libraryId, int $bookId): string
    {
        return $libraryId.':'.$bookId;
    }
}
