<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationQueueService;
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
            throw (new ModelNotFoundException)->setModel(Book::class, [$book->id]);
        }

        $copyStatus = $filters['copy_status'] ?? null;
        $copyLifecycle = $filters['copy_lifecycle'] ?? null;
        $copySearch = trim((string) ($filters['copy_search'] ?? ''));
        $branchId = $filters['branch_id'] ?? null;
        $locationId = $filters['location_id'] ?? null;

        $book->load([
            'publisher:id,name',
            'categories:id,name',
            'authors:id,name',
            'reservations' => function ($q) use ($libraryIds, $user) {
                $q->when(is_array($libraryIds), fn ($reservationQuery) => $reservationQuery->whereIn('library_id', $libraryIds))
                    ->when($user?->role === User::ROLE_STAFF, function ($reservationQuery) use ($user) {
                        $branchId = $user->assignedBranchId($user->activeLibraryId());

                        $reservationQuery->where(function ($scopeQuery) use ($branchId) {
                            $scopeQuery
                                ->where(function ($libraryScopeQuery) {
                                    $libraryScopeQuery
                                        ->where('scope', Reservation::SCOPE_LIBRARY)
                                        ->whereNull('branch_id');
                                })
                                ->orWhere(function ($branchScopeQuery) use ($branchId) {
                                    if ($branchId === null) {
                                        $branchScopeQuery->whereRaw('1 = 0');

                                        return;
                                    }

                                    $branchScopeQuery
                                        ->where('scope', Reservation::SCOPE_BRANCH)
                                        ->where('branch_id', $branchId);
                                });
                        });
                    })
                    ->with([
                        'user:id,name,email,membership_number',
                        'branch:id,name',
                        'pickupBranch:id,name',
                    ])
                    ->orderBy('created_at')
                    ->orderBy('id');
            },
            'bookCopies' => function ($q) use ($libraryIds, $copyStatus, $copyLifecycle, $copySearch, $branchId, $locationId, $user) {
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
                    ->when($copySearch !== '', function ($copyQuery) use ($copySearch) {
                        $search = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $copySearch).'%';

                        $copyQuery->where(function ($searchQuery) use ($search) {
                            $searchQuery
                                ->where('inventory_code', 'like', $search)
                                ->orWhere('barcode', 'like', $search)
                                ->orWhere('qr_code', 'like', $search)
                                ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', $search))
                                ->orWhereHas('location', fn ($locationQuery) => $locationQuery
                                    ->where('name', 'like', $search)
                                    ->orWhere('room', 'like', $search)
                                    ->orWhere('shelf', 'like', $search));
                        });
                    })
                    ->when(! empty($branchId), fn ($copyQuery) => $copyQuery->where('branch_id', $branchId))
                    ->when(! empty($locationId), fn ($copyQuery) => $copyQuery->where('location_id', $locationId))
                    ->with([
                        'branch:id,name',
                        'library:id,name,code,address,city',
                        'location:id,name,room,shelf',
                        'activeLoan' => function ($loanQuery) use ($user) {
                            $loanQuery->select([
                                'id',
                                'library_id',
                                'book_copy_id',
                                'user_id',
                                'status',
                                'due_at',
                                'borrowed_at',
                                'returned_at',
                            ])
                            ->when($user?->role === User::ROLE_STAFF, function ($staffLoanQuery) use ($user) {
                                $branchId = $user->assignedBranchId($user->activeLibraryId());

                                $staffLoanQuery->whereHas('bookCopy', fn ($copyQuery) => $branchId
                                    ? $copyQuery->where('branch_id', $branchId)
                                    : $copyQuery->whereRaw('1 = 0'));
                            })
                            ->with([
                                'user:id,name,email,membership_number',
                            ]);
                        },
                    ])
                    ->orderBy('inventory_code');
            },
        ]);

        $queueService = app(ReservationQueueService::class);

        $book->reservations->each(function ($reservation) use ($queueService) {
            if ($reservation->isPending()) {
                $reservation->setAttribute('queue_position', $queueService->positionFor($reservation));
                $reservation->setAttribute('queue_size', $queueService->queueSize($reservation->library_id, $reservation->book_id));
            }
        });

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
            return Library::query()
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
