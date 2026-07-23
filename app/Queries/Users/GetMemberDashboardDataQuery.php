<?php

namespace App\Queries\Users;

use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;

class GetMemberDashboardDataQuery
{
    public function handle(User $user): array
    {
        $libraryId = $user->activeLibraryId();

        $activeLoans = Loan::query()
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
            ->active()
            ->with(['bookCopy.book:id,slug,title,subtitle,isbn'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $activeReservations = Reservation::query()
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
            ->active()
            ->with(['book:id,slug,title,subtitle,isbn', 'library:id,name', 'pickupBranch:id,name'])
            ->latest('reserved_at')
            ->limit(5)
            ->get();

        $recentNotifications = $user->notifications()
            ->latest()
            ->limit(5)
            ->get();

        return [
            'member' => $user->load('library:id,name,email,phone,address,city'),
            'activeLoansCount' => $activeLoans->count(),
            'activeReservationsCount' => Reservation::query()
                ->where('user_id', $user->id)
                ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
                ->active()
                ->count(),
            'overdueLoansCount' => Loan::query()
                ->where('user_id', $user->id)
                ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
                ->where('status', 'vėluoja')
                ->whereNull('returned_at')
                ->count(),
            'unreadNotificationsCount' => $user->unreadNotifications()->count(),
            'activeLoans' => $activeLoans,
            'activeReservations' => $activeReservations,
            'recentNotifications' => $recentNotifications,
        ];
    }
}
