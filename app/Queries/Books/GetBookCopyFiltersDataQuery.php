<?php

namespace App\Queries\Books;

use App\Models\Book;
use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;

class GetBookCopyFiltersDataQuery
{
    /**
     * @return array{copyBranches: Collection<int, Branch>, copyLocations: Collection<int, Location>}
     */
    public function handle(User $user, Book $book): array
    {
        $libraryIds = $user->role === 'narys'
            ? $user->manageableLibraryIds()
            : ($user->isSuperAdmin() ? null : [$user->activeLibraryId()]);

        $visibleCopiesQuery = $book->bookCopies()
            ->when(is_array($libraryIds), fn ($query) => $query
                ->withoutGlobalScope('library')
                ->whereIn('library_id', array_filter($libraryIds)));

        return [
            'copyBranches' => Branch::query()
                ->whereIn('id', (clone $visibleCopiesQuery)->whereNotNull('branch_id')->select('branch_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'copyLocations' => Location::query()
                ->whereIn('id', (clone $visibleCopiesQuery)->whereNotNull('location_id')->select('location_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'room', 'shelf']),
        ];
    }
}








