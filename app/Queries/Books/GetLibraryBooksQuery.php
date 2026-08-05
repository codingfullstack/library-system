<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetLibraryBooksQuery
{
    public function handle(?User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryId = $filters['category_id'] ?? null;
        $authorId = $filters['author_id'] ?? null;
        $publisherId = $filters['publisher_id'] ?? null;
        $availability = $filters['availability'] ?? null;
        $libraryIds = $this->visibleLibraryIds($user, $filters);
        $branchId = $this->visibleBranchId($user, $filters, $libraryIds);
        $sort = $filters['sort'] ?? 'title';
        $direction = strtolower($filters['direction'] ?? 'asc');

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $allowedSorts = [
            'title',
            'publication_year',
            'copies_count',
            'created_at',
            'updated_at',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'title';
        }

        $query = Book::query()
            ->with([
                'publisher:id,name',
                'categories:id,name',
                'authors:id,name',
            ])
            ->when($user?->role === 'narys' || $user === null, fn ($builder) => $builder->with([
                'bookCopies' => fn ($copyQuery) => $copyQuery
                    ->withoutGlobalScope('library')
                    ->whereIn('library_id', $libraryIds)
                    ->with('library:id,name,code,address,city')
                    ->orderBy('library_id')
                    ->orderBy('inventory_code'),
            ]))
            ->select([
                'id',
                'slug',
                'title',
                'subtitle',
                'isbn',
                'description',
                'publication_year',
                'language',
                'page_count',
                'cover_image',
                'publisher_id',
                'category_id',
                'created_at',
                'updated_at',
            ])
            ->when(
                is_array($libraryIds),
                fn ($builder) => $libraryIds === []
                    ? $builder->whereRaw('1 = 0')
                    : $builder->whereHas('bookCopies', fn ($copyQuery) => $copyQuery
                        ->withoutGlobalScope('library')
                        ->whereIn('library_id', $libraryIds))
            )
            ->when(
                filled($filters['branch_id'] ?? null) && $branchId === null,
                fn ($builder) => $builder->whereRaw('1 = 0')
            )
            ->when(
                $branchId,
                fn ($builder) => $builder->whereHas('bookCopies', fn ($copyQuery) => $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId))
            )
            ->withCount([
                'bookCopies as copies_count' => function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);
                },
                'bookCopies as available_copies_count' => function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);

                    $copyQuery->where('status', BookCopy::STATUS_AVAILABLE);
                },
                'bookCopies as loaned_copies_count' => function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);

                    $copyQuery->where('status', BookCopy::STATUS_LOANED);
                },
                'bookCopies as unavailable_copies_count' => function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);

                    $copyQuery->whereIn('status', [
                        BookCopy::STATUS_LOST,
                        BookCopy::STATUS_MAINTENANCE,
                        BookCopy::STATUS_WITHDRAWN,
                    ]);
                },
                'reservations as active_reservations_count' => function ($reservationQuery) use ($libraryIds) {
                    if (is_array($libraryIds)) {
                        $reservationQuery->whereIn('library_id', $libraryIds);
                    }

                    $reservationQuery->active();
                },
                'reservations as current_user_active_reservations_count' => function ($reservationQuery) use ($user, $libraryIds) {
                    if ($user?->effectiveRole($user->activeLibraryId()) !== User::ROLE_MEMBER) {
                        $reservationQuery->whereRaw('1 = 0');

                        return;
                    }

                    if (is_array($libraryIds)) {
                        $reservationQuery->whereIn('library_id', $libraryIds);
                    }

                    $reservationQuery
                        ->where('user_id', $user->id)
                        ->active();
                },
                'loans as current_user_active_loans_count' => function ($loanQuery) use ($user, $libraryIds) {
                    if ($user?->effectiveRole($user->activeLibraryId()) !== User::ROLE_MEMBER) {
                        $loanQuery->whereRaw('1 = 0');

                        return;
                    }

                    if (is_array($libraryIds)) {
                        $loanQuery->whereIn('loans.library_id', $libraryIds);
                    }

                    $loanQuery
                        ->where('loans.user_id', $user->id)
                        ->whereNull('loans.returned_at')
                        ->whereIn('loans.status', Loan::ACTIVE_STATUSES);
                },
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('authors', function ($authorQuery) use ($search) {
                        $authorQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('publisher', function ($publisherQuery) use ($search) {
                        $publisherQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('categories', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($categoryId)) {
            $query->whereHas('categories', function ($categoryQuery) use ($categoryId) {
                $categoryQuery->where('categories.id', $categoryId);
            });
        }

        if (! empty($authorId)) {
            $query->whereHas('authors', function ($authorQuery) use ($authorId) {
                $authorQuery->where('authors.id', $authorId);
            });
        }

        if (! empty($publisherId)) {
            $query->where('publisher_id', $publisherId);
        }

        if ($availability === BookCopy::STATUS_AVAILABLE) {
            $query->whereHas('bookCopies', function ($copyQuery) use ($libraryIds, $branchId) {
                $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);

                $copyQuery->where('status', BookCopy::STATUS_AVAILABLE);
            });
        }

        if ($availability === 'unavailable') {
            $query
                ->whereHas('bookCopies', function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);
                })
                ->whereDoesntHave('bookCopies', function ($copyQuery) use ($libraryIds, $branchId) {
                    $this->applyCopyVisibility($copyQuery, $libraryIds, $branchId);

                    $copyQuery->where('status', BookCopy::STATUS_AVAILABLE);
                });
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return list<int>|null
     */
    private function visibleLibraryIds(?User $user, array $filters): ?array
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
            $libraryId = $filters['library_id'] ?? null;

            return $libraryId ? [(int) $libraryId] : null;
        }

        if ($user->role === 'narys') {
            return $user->manageableLibraryIds();
        }

        $libraryId = $user->activeLibraryId();

        return $libraryId ? [(int) $libraryId] : [];
    }

    private function visibleBranchId(?User $user, array $filters, ?array $libraryIds): ?int
    {
        $branchId = filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;

        if (
            ! $branchId
            && ($filters['scope_to_assigned_branch'] ?? false)
            && $user?->role === User::ROLE_STAFF
            && ! $user->isAdmin()
        ) {
            return $user->assignedBranchId($user->activeLibraryId()) ?? -1;
        }

        if (! $branchId) {
            return null;
        }

        $query = Branch::query()->whereKey($branchId);

        if (is_array($libraryIds)) {
            if ($libraryIds === []) {
                return null;
            }

            $query->withoutGlobalScope('library')->whereIn('library_id', $libraryIds);
        }

        if ($user?->role === User::ROLE_STAFF && ! $user->isAdmin()) {
            $assignedBranchId = $user->assignedBranchId($user->activeLibraryId());

            if ($assignedBranchId === null || $branchId !== $assignedBranchId) {
                return null;
            }
        }

        return $query->exists() ? $branchId : null;
    }

    private function applyCopyVisibility($copyQuery, ?array $libraryIds, ?int $branchId): void
    {
        if (is_array($libraryIds)) {
            $copyQuery->withoutGlobalScope('library')->whereIn('library_id', $libraryIds);
        }

        if ($branchId) {
            $copyQuery->where('branch_id', $branchId);
        }
    }
}
