<?php

namespace App\Queries\Reservations;

use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Collection;

class GetReservationIndexFiltersDataQuery
{
    /**
     * @return array{libraries: Collection<int, Library>}
     */
    public function handle(User $user): array
    {
        return [
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}
