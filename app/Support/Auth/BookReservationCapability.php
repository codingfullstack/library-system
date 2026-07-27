<?php

namespace App\Support\Auth;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;

class BookReservationCapability
{
    public function canReserve(User $user, Book $book, ?int $libraryId = null): bool
    {
        $libraryId ??= $user->activeLibraryId();

        if (! $libraryId || ! $user->hasAnyEffectiveRole([
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_STAFF,
            User::ROLE_MEMBER,
        ], $libraryId)) {
            return false;
        }

        if ((int) ($book->copies_count ?? 0) <= 0 || (int) ($book->available_copies_count ?? 0) > 0) {
            return false;
        }

        if ($user->effectiveRole($libraryId) !== User::ROLE_MEMBER) {
            return true;
        }

        return ! $this->memberHasBlockingLoan($user, $book, $libraryId)
            && ! $this->memberHasActiveReservation($user, $book, $libraryId);
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
}
