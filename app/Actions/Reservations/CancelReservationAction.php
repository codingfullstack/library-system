<?php

namespace App\Actions\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function handle(User $actor, Reservation $reservation): Reservation
    {
        if ($reservation->library_id !== $actor->library_id) {
            throw ValidationException::withMessages([
                'reservation' => 'Negalite atšaukti kitos bibliotekos rezervacijos.',
            ]);
        }

        if (! $reservation->isActive()) {
            throw ValidationException::withMessages([
                'reservation' => 'Galima atšaukti tik aktyvią rezervaciją.',
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $reservation->fresh();
    }
}
