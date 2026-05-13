<?php

namespace App\Queries\Management\Branches;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManageBranchesQuery
{
    public function handle(User $user, string|array $search = ''): LengthAwarePaginator
    {
        $filters = is_array($search) ? $search : ['search' => $search];
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Branch::query()
            ->with(['library:id,name'])
            ->withCount(['locations', 'bookCopies'])
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('library_id', $user->activeLibraryId()))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}








