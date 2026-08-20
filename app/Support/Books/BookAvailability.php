<?php

namespace App\Support\Books;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationQueueService;

class BookAvailability
{
    public function __construct(
        private readonly ReservationQueueService $queueService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBook(Book $book, ?User $user = null, ?int $libraryId = null): array
    {
        $libraryId ??= $user?->activeLibraryId();

        $copiesCount = $this->countFromAttributeOrQuery($book, 'total_copies_count', function () use ($book, $libraryId): int {
            if (array_key_exists('copies_count', $book->getAttributes())) {
                return (int) $book->getAttribute('copies_count');
            }

            return $book->bookCopies()
                ->when($libraryId, fn ($query) => $query->withoutGlobalScope('library')->where('library_id', $libraryId))
                ->count();
        });

        $availableCopiesCount = $this->countFromAttributeOrQuery($book, 'available_copies_count', function () use ($book, $libraryId): int {
            return $book->bookCopies()
                ->when($libraryId, fn ($query) => $query->withoutGlobalScope('library')->where('library_id', $libraryId))
                ->operationallyAvailable()
                ->count();
        });

        $activeReservationsCount = $this->countFromAttributeOrQuery($book, 'active_reservations_count', function () use ($book, $libraryId): int {
            if (! $libraryId) {
                return 0;
            }

            return Reservation::query()
                ->where('library_id', $libraryId)
                ->where('book_id', $book->id)
                ->active()
                ->count();
        });

        $readyReservationsCount = $this->countFromAttributeOrQuery($book, 'ready_reservations_count', function () use ($book, $libraryId): int {
            if (! $libraryId) {
                return 0;
            }

            return Reservation::query()
                ->where('library_id', $libraryId)
                ->where('book_id', $book->id)
                ->where('status', Reservation::STATUS_READY)
                ->whereNull('fulfilled_at')
                ->whereNull('cancelled_at')
                ->count();
        });

        $waitingReservationsCount = $this->countFromAttributeOrQuery($book, 'waiting_reservations_count', function () use ($book, $libraryId): int {
            if (array_key_exists('pending_reservations_count', $book->getAttributes())) {
                return (int) $book->getAttribute('pending_reservations_count');
            }

            if (! $libraryId) {
                return 0;
            }

            return $this->queueService->queueSize($libraryId, (int) $book->id);
        });

        $currentUserReservation = $this->currentUserReservation($book, $user, $libraryId);
        $currentUserQueuePosition = $currentUserReservation?->isPending()
            ? $this->queueService->positionFor($currentUserReservation)
            : null;

        $hasAvailableCopies = $availableCopiesCount > 0;
        $hasWaitingQueue = $waitingReservationsCount > 0;
        $statusCode = $hasAvailableCopies ? 'available' : 'unavailable';

        return [
            'total_copies_count' => $copiesCount,
            'copies_count' => $copiesCount,
            'available_copies_count' => $availableCopiesCount,
            'active_reservations_count' => $activeReservationsCount,
            'ready_reservations_count' => $readyReservationsCount,
            'waiting_reservations_count' => $waitingReservationsCount,
            'has_waiting_queue' => $hasWaitingQueue,
            'has_reservation_queue' => $hasWaitingQueue,
            'reservation_queue_size' => $waitingReservationsCount,
            'availability_status' => $statusCode,
            'availability_label' => $this->labelFor($statusCode),
            'availability_reason' => $this->reasonFor($statusCode),
            'display_status' => $this->labelFor($statusCode),
            'is_available' => $statusCode === 'available',
            'current_user_reservation' => $currentUserReservation ? [
                'id' => $currentUserReservation->id,
                'status' => $currentUserReservation->status,
                'is_pending' => $currentUserReservation->isPending(),
                'is_ready' => $currentUserReservation->isReady(),
                'queue_position' => $currentUserQueuePosition,
                'pickup_branch_id' => $currentUserReservation->pickup_branch_id,
                'assigned_book_copy_id' => $currentUserReservation->assigned_book_copy_id,
            ] : null,
            'current_user_queue_position' => $currentUserQueuePosition,
            'can_reserve' => $user
                ? $this->computeCanReserve($user, $book, $libraryId, $copiesCount, $hasAvailableCopies)
                : false,
            'cannot_reserve_reason' => $user
                ? $this->cannotReserveReason($user, $book, $libraryId, $copiesCount, $hasAvailableCopies)
                : 'unauthenticated',
        ];
    }

    public function canReserve(User $user, Book $book, ?int $libraryId = null): bool
    {
        $availability = $this->forBook($book, $user, $libraryId);

        return (bool) $availability['can_reserve'];
    }

    private function computeCanReserve(
        User $user,
        Book $book,
        ?int $libraryId,
        int $copiesCount,
        bool $hasAvailableCopies
    ): bool {
        return $this->cannotReserveReason($user, $book, $libraryId, $copiesCount, $hasAvailableCopies) === null;
    }

    private function cannotReserveReason(
        User $user,
        Book $book,
        ?int $libraryId,
        int $copiesCount,
        bool $hasAvailableCopies
    ): ?string {
        if (! $libraryId || ! $user->hasAnyEffectiveRole([
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_STAFF,
            User::ROLE_MEMBER,
        ], $libraryId)) {
            return 'no_active_library_membership';
        }

        if ($copiesCount <= 0) {
            return 'no_copies';
        }

        if ($user->effectiveRole($libraryId) === User::ROLE_MEMBER) {
            if ($this->memberHasBlockingLoan($user, $book, $libraryId)) {
                return 'current_user_has_active_loan';
            }

            if ($this->memberHasActiveReservation($user, $book, $libraryId)) {
                return 'current_user_has_active_reservation';
            }
        }

        if ($hasAvailableCopies) {
            return 'available_without_queue';
        }

        return null;
    }

    private function currentUserReservation(Book $book, ?User $user, ?int $libraryId): ?Reservation
    {
        if (! $user || ! $libraryId || $user->effectiveRole($libraryId) !== User::ROLE_MEMBER) {
            return null;
        }

        if ($book->relationLoaded('reservations')) {
            return $book->reservations
                ->first(fn (Reservation $reservation) => (int) $reservation->library_id === (int) $libraryId
                    && (int) $reservation->user_id === (int) $user->id
                    && $reservation->isActive());
        }

        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->active()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private function memberHasBlockingLoan(User $user, Book $book, int $libraryId): bool
    {
        if (array_key_exists('current_user_active_loans_count', $book->getAttributes())) {
            return (int) $book->getAttribute('current_user_active_loans_count') > 0;
        }

        return Loan::query()
            ->where('library_id', $libraryId)
            ->where('user_id', $user->id)
            ->active()
            ->whereHas('bookCopy', fn ($copyQuery) => $copyQuery->where('book_id', $book->id))
            ->exists();
    }

    private function memberHasActiveReservation(User $user, Book $book, int $libraryId): bool
    {
        if (array_key_exists('current_user_active_reservations_count', $book->getAttributes())) {
            return (int) $book->getAttribute('current_user_active_reservations_count') > 0;
        }

        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->where('user_id', $user->id)
            ->active()
            ->exists();
    }

    private function countFromAttributeOrQuery(Book $book, string $attribute, callable $query): int
    {
        if (array_key_exists($attribute, $book->getAttributes())) {
            return (int) $book->getAttribute($attribute);
        }

        return (int) $query();
    }

    private function labelFor(string $statusCode): string
    {
        return $statusCode === 'available' ? 'Galima' : 'Neprieinama';
    }

    private function reasonFor(string $statusCode): ?string
    {
        return $statusCode === 'unavailable' ? 'Knyga šiuo metu neprieinama.' : null;
    }
}
