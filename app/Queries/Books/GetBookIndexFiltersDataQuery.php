<?php

namespace App\Queries\Books;

use App\Models\Author;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Library;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Support\Collection;

class GetBookIndexFiltersDataQuery
{
    /**
     * @return array{categories: Collection<int, Category>, authors: Collection<int, Author>, publishers: Collection<int, Publisher>, libraries: Collection<int, Library>, branches: Collection<int, Branch>}
     */
    public function handle(?User $user, array $filters = []): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'publishers' => Publisher::query()->orderBy('name')->get(['id', 'name']),
            'libraries' => $user?->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'branches' => $this->branches($user, $filters),
        ];
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(?User $user, array $filters): Collection
    {
        if ($user === null || $user->role === User::ROLE_MEMBER) {
            return collect();
        }

        $query = Branch::query()->orderBy('name');

        if ($user->isSuperAdmin()) {
            if (filled($filters['library_id'] ?? null)) {
                $query->withoutGlobalScope('library')->where('library_id', (int) $filters['library_id']);
            }

            return $query->get(['id', 'name', 'library_id']);
        }

        $libraryId = $user->activeLibraryId();

        if (! $libraryId) {
            return collect();
        }

        $query->withoutGlobalScope('library')->where('library_id', $libraryId);

        if ($user->role === User::ROLE_STAFF && ! $user->isAdmin()) {
            $branchId = $user->assignedBranchId($libraryId);

            if (! $branchId) {
                return collect();
            }

            $query->whereKey($branchId);
        }

        return $query->get(['id', 'name', 'library_id']);
    }
}
