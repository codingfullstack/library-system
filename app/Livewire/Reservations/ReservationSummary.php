<?php

namespace App\Livewire\Reservations;

use App\Models\Book;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ReservationSummary extends Component
{
    public int $bookId;

    public function mount(Book $book): void
    {
        $this->bookId = $book->id;
    }

    #[On('reservation-created')]
    #[On('reservation-updated')]
    public function refreshSummary(): void
    {
    }

    public function render()
    {
        $actor = Auth::user();

        if (! $actor) {
            return view('livewire.reservations.reservation-summary', [
                'activeCount' => 0,
                'firstActiveReservation' => null,
            ]);
        }

        $currentReservations = Reservation::query()
            ->where('book_id', $this->bookId)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->with('user:id,name,email,membership_number')
            ->pending()
            ->orderBy('reserved_at')
            ->get();

        return view('livewire.reservations.reservation-summary', [
            'activeCount' => $currentReservations->count(),
            'firstActiveReservation' => $currentReservations->first(),
        ]);
    }
}








