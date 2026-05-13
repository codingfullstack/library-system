<?php

namespace App\Queries\Management\Locations;

use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\User;

class GetManageLocationFormDataQuery
{
    public function handle(User $user, Location $location): array
    {
        $selectedLibraryId = $user->isSuperAdmin()
            ? ($location->library_id ?: old('library_id'))
            : $user->activeLibraryId();

        return [
            'location' => $location,
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'branches' => $selectedLibraryId
                ? Branch::query()->where('library_id', $selectedLibraryId)->orderBy('name')->get(['id', 'name', 'code'])
                : collect(),
        ];
    }
}








