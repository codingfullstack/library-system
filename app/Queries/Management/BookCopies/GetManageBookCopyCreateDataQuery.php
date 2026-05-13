<?php

namespace App\Queries\Management\BookCopies;

use App\Models\Book;
use App\Models\Library;
use App\Models\User;

class GetManageBookCopyCreateDataQuery
{
    public function handle(User $user, array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $selectedBookId = $filters['book_id'] ?? null;
        $selectedLibraryId = $user->isSuperAdmin()
            ? (($filters['library_id'] ?? null) ?: old('library_id'))
            : $user->activeLibraryId();

        $books = Book::query()
            ->with(['authors:id,name', 'publisher:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhereHas('authors', fn ($authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('publisher', fn ($publisherQuery) => $publisherQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('title')
            ->limit($selectedBookId ? 20 : 15)
            ->get();

        return [
            'books' => $books,
            'selectedBook' => $selectedBookId
                ? Book::query()->with(['authors:id,name', 'publisher:id,name', 'categories:id,name'])->find($selectedBookId)
                : null,
            'selectedLibraryId' => $selectedLibraryId,
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}








