<?php

namespace App\Queries\Users;

use App\Models\User;
use App\Support\UserManagement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetManageUsersQuery
{
    public function handle(User $actor, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $active = $filters['aktyvi'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

        return UserManagement::scopeVisibleUsers(
            User::query()->with('libraryMemberships.library:id,name,code'),
            $actor
        )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('membership_number', 'like', "%{$search}%");
                });
            })
            ->when($role && in_array($role, UserManagement::manageableRoles($actor), true), fn ($query) => $query->where('role', $role))
            ->when($active !== null && $active !== '', function ($query) use ($actor, $active) {
                if ($actor->isSuperAdmin()) {
                    $query->where('is_active', $active === '1');

                    return;
                }

                $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $actor->activeLibraryId())
                    ->where('is_active', $active === '1'));
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
