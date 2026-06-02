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

    public ?string $message = null;

    public function open(): void
    {
        $this->message = null;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        try {
            $reservation = app(CancelReservationAction::class)->handle(
                $actor,
                $this->reservation->fresh(),
                $this->reason
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? 'Nepavyko atšaukti rezervacijos.');
            }

            return;
        }

        $this->reservation = $reservation->fresh(['book']);
        $this->isOpen = false;
        $this->reason = '';
        $this->message = 'Rezervacija atšaukta.';

        $this->dispatch('reservation-updated', bookId: $this->reservation->book_id, reservationId: $this->reservation->id);
        $this->dispatch('reservation-cancelled', reservationId: $this->reservation->id);
    }

    public function render()
    {
        $actor = Auth::user();

        return view('livewire.reservations.cancel-reservation-form', [
            'requiresReason' => $actor && $actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas', 'superadministratorius'], $this->reservation->library_id),
        ]);
    }
}
