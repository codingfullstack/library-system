<?php

namespace App\Queries\Loans;

use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Collection;

class GetLoanIndexFiltersDataQuery
{
    /**
     * @return array{members: Collection<int, User>, employees: Collection<int, User>, libraries: Collection<int, Library>}
     */
    public function handle(User $user, ?int $selectedLibraryId = null): array
    {
        $libraryId = $user->isSuperAdmin() ? $selectedLibraryId : $user->library_id;

        return [
            'members' => User::query()
                ->when(! empty($libraryId), fn ($query) => $query->where('library_id', $libraryId))
                ->where('role', 'member')
                ->orderBy('name')
                ->get(['id', 'name', 'membership_number']),
            'employees' => User::query()
                ->when(! empty($libraryId), fn ($query) => $query->where('library_id', $libraryId))
                ->whereIn('role', ['super_admin', 'admin', 'staff'])
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}
