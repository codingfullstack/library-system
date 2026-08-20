<?php

namespace App\Support\Auth;

use App\Models\Book;
use App\Models\User;
use App\Support\Books\BookAvailability;

class BookReservationCapability
{
    public function __construct(
        private readonly BookAvailability $availability
    ) {}

    public function canReserve(User $user, Book $book, ?int $libraryId = null): bool
    {
        return $this->availability->canReserve($user, $book, $libraryId);
    }
}
