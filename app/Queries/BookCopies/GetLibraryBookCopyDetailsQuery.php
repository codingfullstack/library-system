<?php

namespace App\Queries\BookCopies;

use App\Models\BookCopy;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetLibraryBookCopyDetailsQuery
{
    public function handle(User $user, BookCopy $bookCopy): BookCopy
    {
        $libraryId = $user->activeLibraryId();

        $query = BookCopy::query()
            ->whereKey($bookCopy->id)
            ->with([
                'book:id,slug,title,subtitle,isbn',
                'branch:id,name',
                'location:id,name,room,shelf',
                'statusHistories' => function ($historyQuery) {
                    $historyQuery->select([
                        'id',
                        'book_copy_id',
                        'changed_by',
                        'from_status',
                        'to_status',
                        'reason_code',
                        'reason_notes',
                        'changed_at',
                    ])->with('user:id,name');
                },
                'activeLoan' => function ($loanQuery) {
                    $loanQuery->select([
                        'id',
                        'library_id',
                        'book_copy_id',
                        'user_id',
                        'issued_by',
                        'received_by',
                        'borrowed_at',
                        'due_at',
                        'returned_at',
                        'status',
                        'renewal_count',
                        'notes',
                    ])->with([
                        'user:id,name,email,membership_number',
                        'issuer:id,name,email',
                        'receiver:id,name,email',
                    ]);
                },
                'book.reservations' => function ($reservationQuery) use ($libraryId) {
                    $reservationQuery->where('library_id', $libraryId)
                        ->with([
                            'pickupBranch:id,name',
                            'user:id,name,email,membership_number',
                        ])
                        ->orderBy('reserved_at');
                },
            ]);

        if (! $user->isSuperAdmin()) {
            $query->where('library_id', $libraryId);
        }

        $copy = $query->first();

        if (! $copy) {
            throw (new ModelNotFoundException)->setModel(BookCopy::class, [$bookCopy->id]);
        }

        return $copy;
    }
}
