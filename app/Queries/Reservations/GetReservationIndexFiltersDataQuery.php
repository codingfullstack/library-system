<?php

namespace App\Queries\Reservations;

use App\Models\Branch;
use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Collection;

class GetReservationIndexFiltersDataQuery
{
    /**
     * @return array{libraries: Collection<int, Library>, branches: Collection<int, Branch>}
     */
    public function handle(User $user, array $filters = []): array
    {
        $libraryId = $user->isSuperAdmin()
            ? (filled($filters['library_id'] ?? null) ? (int) $filters['library_id'] : null)
            : $user->activeLibraryId();

        return [
            'libraries' => $user->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'branches' => $this->branches($user, $libraryId),
        ];
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(User $user, ?int $libraryId): Collection
    {
        if ($user->role === User::ROLE_MEMBER || (! $user->isSuperAdmin() && ! $libraryId)) {
            return collect();
        }

        $query = Branch::query()->orderBy('name');

        if ($libraryId) {
            $query->withoutGlobalScope('library')->where('library_id', $libraryId);
        }

        if ($user->role === User::ROLE_STAFF) {
            $branchId = $user->assignedBranchId($libraryId);

            if (! $branchId) {
                return collect();
            }

            $query->whereKey($branchId);
        }

        return $query->get(['id', 'name', 'library_id']);
    }
}
