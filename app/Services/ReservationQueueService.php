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
        return $this->pendingReservationsQuery($libraryId, $bookId)
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
        return $this->getQueuePosition($reservation);
    }

    public function getQueuePosition(Reservation $reservation): ?int
    {
        if (! $reservation->isPending()) {
            return null;
        }

        $position = 0;

        $this->pendingReservationsQuery($reservation->library_id, $reservation->book_id)
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

    /**
     * @return array<int, int>
     */
    public function snapshotPositions(int $libraryId, int $bookId): array
    {
        return $this->getPositionsForBook($libraryId, $bookId);
    }

    /**
     * @return array<int, int>
     */
    public function getPositionsForBook(int $libraryId, int $bookId): array
    {
        return $this->pendingReservations($libraryId, $bookId)
            ->mapWithKeys(fn (Reservation $reservation) => [
                (int) $reservation->id => (int) $reservation->getAttribute('queue_position'),
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function snapshotPositionsBeforeExpiringElapsed(int $libraryId, int $bookId): array
    {
        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->where('status', Reservation::STATUS_RESERVED)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->values()
            ->mapWithKeys(fn (Reservation $reservation, int $index) => [
                (int) $reservation->id => $index + 1,
            ])
            ->all();
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
            ->whereIn('status', [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE])
            ->whereHas('bookCopy', fn (Builder $query) => $query
                ->where('book_id', $bookId))
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
            ->pending()
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function serviceablePendingReservationsQuery(int $libraryId, int $bookId, int $branchId): Builder
    {
        return $this->pendingReservationsQuery($libraryId, $bookId)
            ->where(function (Builder $query) use ($branchId) {
                $this->scopeToServiceableBranch($query, $branchId);
            });
    }

    public function waitingForAssignmentQuery(int $libraryId, int $bookId): Builder
    {
        return $this->pendingReservationsQuery($libraryId, $bookId)
            ->whereNull('expires_at');
    }

    public function serviceableWaitingForAssignmentQuery(int $libraryId, int $bookId, int $branchId): Builder
    {
        return $this->waitingForAssignmentQuery($libraryId, $bookId)
            ->where(function (Builder $query) use ($branchId) {
                $this->scopeToServiceableBranch($query, $branchId);
            });
    }

    public function firstWaitingForAssignmentIfServiceableByBranch(int $libraryId, int $bookId, int $branchId): ?Reservation
    {
        $reservation = $this->waitingForAssignmentQuery($libraryId, $bookId)
            ->lockForUpdate()
            ->first();

        if (! $reservation || ! $this->canBeServedByBranch($reservation, $branchId)) {
            return null;
        }

        return $reservation;
    }

    public function canBeServedByBranch(Reservation $reservation, int $branchId): bool
    {
        if ($reservation->scope === Reservation::SCOPE_BRANCH) {
            return (int) $reservation->branch_id === $branchId;
        }

        return ($reservation->scope ?: Reservation::SCOPE_LIBRARY) === Reservation::SCOPE_LIBRARY
            && $reservation->branch_id === null;
    }

    public function hasWaitingReservationBefore(Reservation $reservation): bool
    {
        return $this->waitingForAssignmentQuery($reservation->library_id, $reservation->book_id)
            ->where(function (Builder $query) use ($reservation) {
                $query->where('created_at', '<', $reservation->created_at)
                    ->orWhere(function (Builder $sameTimeQuery) use ($reservation) {
                        $sameTimeQuery
                            ->where('created_at', '=', $reservation->created_at)
                            ->where('id', '<', $reservation->id);
                    });
            })
            ->exists();
    }

    private function scopeToServiceableBranch(Builder $query, int $branchId): void
    {
        $query->where(function (Builder $libraryScopeQuery) {
            $libraryScopeQuery
                ->where('scope', Reservation::SCOPE_LIBRARY)
                ->whereNull('branch_id');
        })->orWhere(function (Builder $branchScopeQuery) use ($branchId) {
            $branchScopeQuery
                ->where('scope', Reservation::SCOPE_BRANCH)
                ->where('branch_id', $branchId);
        });
    }
}
