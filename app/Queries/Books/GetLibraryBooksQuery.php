<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetLibraryBooksQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryId = $filters['category_id'] ?? null;
        $authorId = $filters['author_id'] ?? null;
        $publisherId = $filters['publisher_id'] ?? null;
        $availability = $filters['availability'] ?? null;
        $libraryId = $user->isSuperAdmin() ? ($filters['library_id'] ?? null) : $user->library_id;
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
            ->select([
                'id',
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
                ! empty($libraryId),
                fn ($builder) => $builder->whereHas('bookCopies', fn ($copyQuery) => $copyQuery->where('library_id', $libraryId))
            )
            ->withCount([
                'bookCopies as copies_count' => function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }
                },
                'bookCopies as available_copies_count' => function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }

                    $copyQuery->where('status', 'available');
                },
                'bookCopies as loaned_copies_count' => function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }

                    $copyQuery->whereIn('status', ['loaned', 'overdue']);
                },
                'bookCopies as unavailable_copies_count' => function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }

                    $copyQuery->whereIn('status', ['lost', 'damaged', 'maintenance', 'withdrawn']);
                },
                'reservations as active_reservations_count' => function ($reservationQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $reservationQuery->where('library_id', $libraryId);
                    }

                    $reservationQuery->active();
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

        if ($availability === 'available') {
            $query->whereHas('bookCopies', function ($copyQuery) use ($libraryId) {
                if (! empty($libraryId)) {
                    $copyQuery->where('library_id', $libraryId);
                }

                $copyQuery->where('status', 'available');
            });
        }

        if ($availability === 'unavailable') {
            $query
                ->whereHas('bookCopies', function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }
                })
                ->whereDoesntHave('bookCopies', function ($copyQuery) use ($libraryId) {
                    if (! empty($libraryId)) {
                        $copyQuery->where('library_id', $libraryId);
                    }

                    $copyQuery->where('status', 'available');
                });
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }
}
