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
        $libraryId = $user->isSuperAdmin() ? $selectedLibraryId : $user->activeLibraryId();

        return [
            'members' => User::query()
                ->when(! empty($libraryId), fn ($query) => $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true)))
                ->where('role', 'narys')
                ->orderBy('name')
                ->get(['id', 'name', 'membership_number']),
            'employees' => User::query()
                ->when(! empty($libraryId), fn ($query) => $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true)))
                ->whereIn('role', ['superadministratorius', 'administratorius', 'darbuotojas'])
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];
    }
}








