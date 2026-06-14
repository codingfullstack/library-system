<?php

namespace App\Queries\Users;

use App\Models\User;

class GetManagedUserDetailsQuery
{
    public function handle(User $user, ?User $actor = null): User
    {
        $staffBranchId = $actor?->role === User::ROLE_STAFF
            ? $actor->assignedBranchId($actor->activeLibraryId())
            : null;

        $scopeStaffLoans = function ($query) use ($actor, $staffBranchId) {
            if ($actor?->role === User::ROLE_STAFF) {
                $query->whereHas('bookCopy', fn ($copyQuery) => $staffBranchId
                    ? $copyQuery->where('branch_id', $staffBranchId)
                    : $copyQuery->whereRaw('1 = 0'));
            }
        };

        $user->load([
            'libraryMemberships.library:id,name,code',
            'loans' => function ($query) use ($scopeStaffLoans) {
                $scopeStaffLoans($query);

                $query->with('bookCopy.book:id,slug,title')
                    ->latest('borrowed_at')
                    ->limit(5);
            },
            'reservations' => function ($query) {
                $query->with('book:id,slug,title')
                    ->latest('reserved_at')
                    ->limit(5);
            },
        ])->loadCount([
            'loans' => $scopeStaffLoans,
            'loans as active_loans_count' => function ($query) use ($scopeStaffLoans) {
                $scopeStaffLoans($query);
                $query->whereNull('returned_at');
            },
            'reservations',
            'reservations as pending_reservations_count' => fn ($query) => $query->pending(),
            'issuedLoans',
            'receivedLoans',
        ]);

        return $user;
    }
}
