<?php

namespace App\Queries\Management;

use App\Models\Author;
use App\Models\Book;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Location;
use App\Models\Publisher;
use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Database\Eloquent\Builder;

class SearchManagementEntitiesQuery
{
    /**
     * @return array<string, \Illuminate\Support\Collection<int, mixed>>
     */
    public function handle(User $actor, string $search): array
    {
        $results = [
            'users' => collect(),
            'authors' => collect(),
            'branches' => collect(),
            'locations' => collect(),
            'books' => collect(),
            'categories' => collect(),
            'publishers' => collect(),
        ];

        if ($search === '') {
            return $results;
        }

        $results['users'] = UserManagement::scopeVisibleUsers(
            User::query()->with('libraryMemberships.library:id,name,code'),
            $actor
        )
            ->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('membership_number', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        $results['authors'] = Author::query()
            ->where('name', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);

        $results['branches'] = Branch::query()
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->where('library_id', $actor->activeLibraryId()))
            ->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            })
            ->with('library:id,name')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $results['locations'] = Location::query()
            ->when(! $actor->isSuperAdmin(), fn (Builder $query) => $query->where('library_id', $actor->activeLibraryId()))
            ->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('room', 'like', "%{$search}%")
                    ->orWhere('shelf', 'like', "%{$search}%");
            })
            ->with(['library:id,name', 'branch:id,name'])
            ->orderBy('name')
            ->limit(8)
            ->get();

        if (! $actor->isSuperAdmin()) {
            return $results;
        }

        $results['books'] = Book::query()
            ->with(['authors:id,name', 'publisher:id,name'])
            ->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%")
                    ->orWhereHas('authors', fn (Builder $authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('publisher', fn (Builder $publisherQuery) => $publisherQuery->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('title')
            ->limit(8)
            ->get();

        $results['categories'] = Category::query()
            ->where('name', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);

        $results['publishers'] = Publisher::query()
            ->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'country']);

        return $results;
    }
}








