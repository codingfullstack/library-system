<?php

namespace App\Queries\Management\Locations;

use App\Models\Location;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManageLocationsQuery
{
    public function handle(User $user, string|array $search = ''): LengthAwarePaginator
    {
        $filters = is_array($search) ? $search : ['search' => $search];
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Location::query()
            ->with(['library:id,name', 'branch:id,name'])
            ->withCount('bookCopies')
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('library_id', $user->library_id))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('room', 'like', "%{$search}%")
                        ->orWhere('shelf', 'like', "%{$search}%")
                        ->orWhereHas('branch', fn ($branchQuery) => $branchQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
