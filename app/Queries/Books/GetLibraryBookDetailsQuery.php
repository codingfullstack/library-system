<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetLibraryBookDetailsQuery
{
    public function handle(User $user, Book $book): Book
    {
        $hasVisibleCopies = $book->bookCopies()
            ->where('library_id', $user->library_id)
            ->exists();

        if (! $hasVisibleCopies) {
            throw (new ModelNotFoundException())->setModel(Book::class, [$book->id]);
        }

        $book->load([
            'publisher:id,name',
            'category:id,name',
            'authors:id,name',
            'bookCopies' => function ($q) use ($user) {
                $q->where('library_id', $user->library_id)
                    ->with([
                        'branch:id,name',
                        'location:id,name,room,shelf',
                        'activeLoan' => function ($loanQuery) {
                            $loanQuery->select([
                                'id',
                                'book_copy_id',
                                'user_id',
                                'status',
                                'due_at',
                                'borrowed_at',
                                'returned_at',
                            ])->with([
                                'user:id,name,email,membership_number',
                            ]);
                        },
                    ])
                    ->orderBy('inventory_code');
            },
        ]);

        $book->loadCount([
            'bookCopies as copies_count' => function ($q) use ($user) {
                $q->where('library_id', $user->library_id);
            },
        ]);

        return $book;
    }
}