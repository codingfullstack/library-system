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
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'title';
        }

        $libraryFilter = function ($q) use ($user) {
            $q->where('library_id', $user->library_id);
        };

        $query = Book::query()
            ->with([
                'publisher:id,name',
                'category:id,name',
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
                'publisher_id',
                'category_id',
                'created_at',
            ])
            ->whereHas('bookCopies', $libraryFilter)
            ->withCount([
                'bookCopies as copies_count' => $libraryFilter,
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
                    });
            });
        }

        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $query->orderBy($sort, $direction)->orderBy('id');

        return $query->paginate($perPage)->withQueryString();
    }
}