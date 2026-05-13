<?php

namespace App\Livewire\Reservations;

use App\Actions\Reservations\CancelReservationAction;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CancelReservationForm extends Component
{
    public Reservation $reservation;

    public bool $compact = false;

    public bool $isOpen = false;

    public string $reason = '';

    public function open(): void
    {
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->resetErrorBag();
    }

    public function save()
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        try {
            app(CancelReservationAction::class)->handle(
                $actor,
                $this->reservation->fresh(),
                $this->reason
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? 'Nepavyko atšaukti rezervacijos.');
            }

            return null;
        }

        return redirect(request()->fullUrl())->with('success', 'Rezervacija atšaukta.');
    }

    public function render()
    {
        $actor = Auth::user();

        return view('livewire.reservations.cancel-reservation-form', [
            'requiresReason' => $actor && $actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $this->reservation->library_id),
        ]);
    }
}








