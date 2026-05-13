<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas', 'narys']);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->effectiveRole($reservation->library_id) === 'narys') {
            return $reservation->user_id === $user->id
                && $user->belongsToLibrary($reservation->library_id);
        }

        return $user->belongsToLibrary($reservation->library_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas', 'narys']);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->effectiveRole($reservation->library_id) === 'narys') {
            return $reservation->user_id === $user->id
                && $user->belongsToLibrary($reservation->library_id);
        }

        return $user->hasAnyEffectiveRole(['administratorius', 'darbuotojas'], $reservation->library_id)
            && $user->belongsToLibrary($reservation->library_id);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->effectiveRole($reservation->library_id) === 'narys') {
            return $reservation->user_id === $user->id
                && $user->belongsToLibrary($reservation->library_id);
        }

        return $user->hasAnyEffectiveRole(['administratorius'], $reservation->library_id)
            && $user->belongsToLibrary($reservation->library_id);
    }
}








