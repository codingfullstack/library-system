<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetLibraryBookDetailsQuery
{
    public function handle(User $user, Book $book, array $filters = []): Book
    {
        $hasVisibleCopies = $book->bookCopies()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('library_id', $user->library_id))
            ->exists();

        if (! $user->isSuperAdmin() && ! $hasVisibleCopies) {
            throw (new ModelNotFoundException())->setModel(Book::class, [$book->id]);
        }

        $copyStatus = $filters['copy_status'] ?? null;
        $copyLifecycle = $filters['copy_lifecycle'] ?? null;
        $branchId = $filters['branch_id'] ?? null;
        $locationId = $filters['location_id'] ?? null;

        $book->load([
            'publisher:id,name',
            'categories:id,name',
            'authors:id,name',
            'reservations' => function ($q) use ($user) {
                $q->when(! $user->isSuperAdmin(), fn ($reservationQuery) => $reservationQuery->where('library_id', $user->library_id))
                    ->with('user:id,name,email,membership_number')
                    ->orderBy('reserved_at');
            },
            'bookCopies' => function ($q) use ($user, $copyStatus, $copyLifecycle, $branchId, $locationId) {
                $q->when(! $user->isSuperAdmin(), fn ($copyQuery) => $copyQuery->where('library_id', $user->library_id))
                    ->when(! empty($copyLifecycle), function ($copyQuery) use ($copyLifecycle) {
                        match ($copyLifecycle) {
                            'active' => $copyQuery->whereIn('status', ['available', 'loaned']),
                            'issues' => $copyQuery->whereIn('status', ['lost', 'damaged', 'maintenance']),
                            'removed' => $copyQuery->where('status', 'withdrawn'),
                            default => null,
                        };
                    })
                    ->when(! empty($copyStatus), fn ($copyQuery) => $copyQuery->where('status', $copyStatus))
                    ->when(! empty($branchId), fn ($copyQuery) => $copyQuery->where('branch_id', $branchId))
                    ->when(! empty($locationId), fn ($copyQuery) => $copyQuery->where('location_id', $locationId))
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
                if (! $user->isSuperAdmin()) {
                    $q->where('library_id', $user->library_id);
                }
            },
        ]);

        return $book;
    }
}
