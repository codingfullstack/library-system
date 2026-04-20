<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', 'staff', 'member'], true);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === 'member') {
            return $reservation->user_id === $user->id
                && $reservation->library_id === $user->library_id;
        }

        return $user->belongsToLibrary($reservation->library_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'admin', 'staff', 'member'], true);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === 'member') {
            return $reservation->user_id === $user->id
                && $reservation->library_id === $user->library_id;
        }

        return in_array($user->role, ['admin', 'staff'], true)
            && $user->belongsToLibrary($reservation->library_id);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->role === 'member') {
            return $reservation->user_id === $user->id
                && $reservation->library_id === $user->library_id;
        }

        return $user->role === 'admin'
            && $user->belongsToLibrary($reservation->library_id);
    }
}