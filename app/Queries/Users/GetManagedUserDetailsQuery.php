<?php

namespace App\Queries\Users;

use App\Models\User;

class GetManagedUserDetailsQuery
{
    public function handle(User $user): User
    {
        $user->load([
            'libraryMemberships.library:id,name,code',
            'loans' => function ($query) {
                $query->with('bookCopy.book:id,title')
                    ->latest('borrowed_at')
                    ->limit(5);
            },
            'reservations' => function ($query) {
                $query->with('book:id,title')
                    ->latest('reserved_at')
                    ->limit(5);
            },
        ])->loadCount([
            'loans',
            'loans as active_loans_count' => fn ($query) => $query->whereNull('returned_at'),
            'reservations',
            'reservations as pending_reservations_count' => fn ($query) => $query->pending(),
            'issuedLoans',
            'receivedLoans',
        ]);

        return $user;
    }
}








