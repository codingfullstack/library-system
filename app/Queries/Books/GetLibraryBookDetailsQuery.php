<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetLibraryBookDetailsQuery
{
    public function handle(?User $user, Book $book, array $filters = []): Book
    {
        $libraryIds = $this->visibleLibraryIds($user);

        $hasVisibleCopies = $book->bookCopies()
            ->when(is_array($libraryIds), fn ($query) => $query
                ->withoutGlobalScope('library')
                ->whereIn('library_id', $libraryIds))
            ->exists();

        if (($user === null || $user->role === 'narys') && is_array($libraryIds) && ! $hasVisibleCopies) {
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
            'reservations' => function ($q) use ($libraryIds) {
                $q->when(is_array($libraryIds), fn ($reservationQuery) => $reservationQuery->whereIn('library_id', $libraryIds))
                    ->with('user:id,name,email,membership_number')
                    ->orderBy('reserved_at');
            },
            'bookCopies' => function ($q) use ($libraryIds, $copyStatus, $copyLifecycle, $branchId, $locationId) {
                $q->when(is_array($libraryIds), fn ($copyQuery) => $copyQuery
                        ->withoutGlobalScope('library')
                        ->whereIn('library_id', $libraryIds))
                    ->when(! empty($copyLifecycle), function ($copyQuery) use ($copyLifecycle) {
                        match ($copyLifecycle) {
                            'aktyvi' => $copyQuery->whereIn('status', ['laisva', 'išduota']),
                            'issues' => $copyQuery->whereIn('status', ['prarasta', 'sugadinta', 'tvarkoma']),
                            'removed' => $copyQuery->where('status', 'nurašyta'),
                            default => null,
                        };
                    })
                    ->when(! empty($copyStatus), fn ($copyQuery) => $copyQuery->where('status', $copyStatus))
                    ->when(! empty($branchId), fn ($copyQuery) => $copyQuery->where('branch_id', $branchId))
                    ->when(! empty($locationId), fn ($copyQuery) => $copyQuery->where('location_id', $locationId))
                    ->with([
                        'branch:id,name',
                        'library:id,name,code,address,city',
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
            'bookCopies as copies_count' => function ($q) use ($libraryIds) {
                if (is_array($libraryIds)) {
                    $q->withoutGlobalScope('library')->whereIn('library_id', $libraryIds);
                }
            },
            'bookCopies as available_copies_count' => function ($q) use ($libraryIds) {
                if (is_array($libraryIds)) {
                    $q->withoutGlobalScope('library')->whereIn('library_id', $libraryIds);
                }

                $q->where('status', 'laisva');
            },
        ]);

        return $book;
    }

    /**
     * @return list<int>|null
     */
    private function visibleLibraryIds(?User $user): ?array
    {
        if ($user === null) {
            return \App\Models\Library::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->role === 'narys') {
            return $user->manageableLibraryIds();
        }

        $libraryId = $user->activeLibraryId();

        return $libraryId ? [(int) $libraryId] : [];
    }
}








