<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReservationQueueService
{
    /**
     * @return Collection<int, Reservation>
     */
    public function pendingReservations(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): Collection
    {
        return $this->pendingReservationsQuery($libraryId, $bookId, $scope, $branchId)
            ->with(['user:id,name,email', 'book:id,slug,title'])
            ->get()
            ->values()
            ->map(function (Reservation $reservation, int $index) {
                $reservation->setAttribute('queue_position', $index + 1);

                return $reservation;
            });
    }

    public function positionFor(Reservation $reservation): ?int
    {
        if (! $reservation->isPending()) {
            return null;
        }

        $position = 0;

        $this->pendingReservationsQuery(
            $reservation->library_id,
            $reservation->book_id,
            $reservation->scope ?: Reservation::SCOPE_LIBRARY,
            $reservation->branch_id ? (int) $reservation->branch_id : null
        )
            ->select(['id'])
            ->each(function (Reservation $queuedReservation) use ($reservation, &$position) {
                $position++;

                if ((int) $queuedReservation->id === (int) $reservation->id) {
                    return false;
                }

                return null;
            });

        return $position > 0 ? $position : null;
    }

    public function hasAvailableCopies(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): bool
    {
        return BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->when($scope === Reservation::SCOPE_BRANCH, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', BookCopy::STATUS_AVAILABLE)
            ->exists();
    }

    public function activeLoanDueAt(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): ?string
    {
        $dueAt = Loan::query()
            ->where('library_id', $libraryId)
            ->whereNull('returned_at')
            ->whereHas('bookCopy', fn (Builder $query) => $query
                ->where('book_id', $bookId)
                ->when($scope === Reservation::SCOPE_BRANCH, fn (Builder $copyQuery) => $copyQuery->where('branch_id', $branchId)))
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->value('due_at');

        return $dueAt ? Carbon::parse($dueAt)->format('Y-m-d') : null;
    }

    public function pendingReservationsQuery(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): Builder
    {
        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('scope', $scope)
            ->when(
                $scope === Reservation::SCOPE_BRANCH,
                fn (Builder $query) => $query->where('branch_id', $branchId),
                fn (Builder $query) => $query->whereNull('branch_id')
            )
            ->pending()
            ->orderBy('reserved_at')
            ->orderBy('id');
    }
}
