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
                $reservation->setAttribute('queue_size', $this->queueSize($reservation->library_id, $reservation->book_id));

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

    public function queueSize(int $libraryId, int $bookId): int
    {
        return $this->pendingReservationsQuery($libraryId, $bookId)->count();
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
        return $this->snapshotPositions($libraryId, $bookId);
    }

    public function hasAvailableCopies(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): bool
    {
        return $this->availableCopiesQuery($libraryId, $bookId, $scope, $branchId)->exists();
    }

    public function availableCopiesQuery(int $libraryId, int $bookId, string $scope = Reservation::SCOPE_LIBRARY, ?int $branchId = null): Builder
    {
        return BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->when($scope === Reservation::SCOPE_BRANCH, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', BookCopy::STATUS_AVAILABLE)
            ->whereDoesntHave('activeReadyReservation');
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

    public function activeReservationsQuery(int $libraryId, int $bookId): Builder
    {
        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $bookId)
            ->active()
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * The single source of truth for selecting the FIFO reservation a physical
     * copy can serve. Library-scope reservations match every copy; branch-scope
     * reservations only match copies from the same branch.
     *
     * @param  array<int, int>  $exceptReservationIds
     */
    public function getEligibleReservationForCopy(BookCopy $copy, array $exceptReservationIds = [], bool $lockForUpdate = false): ?Reservation
    {
        $query = $this->activeReservationsQuery((int) $copy->library_id, (int) $copy->book_id)
            ->where(function (Builder $scopeQuery) use ($copy) {
                $this->scopeToServiceableBranch($scopeQuery, (int) $copy->branch_id);
            })
            ->where(function (Builder $assignmentQuery) use ($copy) {
                $assignmentQuery->where('status', Reservation::STATUS_WAITING);

                $assignmentQuery->orWhere(function (Builder $readyQuery) use ($copy) {
                    $readyQuery
                        ->where('status', Reservation::STATUS_READY)
                        ->where('assigned_book_copy_id', (int) $copy->id);
                });
            });

        if ($exceptReservationIds !== []) {
            $query->whereNotIn('id', array_values(array_unique(array_map('intval', $exceptReservationIds))));
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $reservation = $query->first();

        if ($reservation?->isPending()) {
            $reservation->setAttribute('queue_position', $this->getQueuePosition($reservation));
            $reservation->syncOriginalAttribute('queue_position');
            $reservation->setAttribute('queue_size', $this->queueSize((int) $reservation->library_id, (int) $reservation->book_id));
            $reservation->syncOriginalAttribute('queue_size');
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
