<?php

namespace App\Queries\Management\Books;

use App\Models\Book;
use App\Models\User;

class FindVisibleManagedBookQuery
{
    public function handle(User $user, Book $book): Book
    {
        if ($user->isSuperAdmin()) {
            return $book;
        }

        return Book::query()
            ->whereKey($book->id)
            ->whereHas('bookCopies', fn ($copyQuery) => $copyQuery->where('library_id', $user->activeLibraryId()))
            ->firstOrFail();
    }
}








