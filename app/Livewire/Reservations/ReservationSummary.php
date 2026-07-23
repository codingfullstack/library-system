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
                'readyReservations' => collect(),
                'waitingReservations' => collect(),
            ]);
        }

        $activeReservations = Reservation::query()
            ->where('book_id', $this->bookId)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->when($actor->role === \App\Models\User::ROLE_STAFF, function ($query) use ($actor) {
                $branchId = $actor->assignedBranchId($actor->activeLibraryId());

                $query->where(function ($scopeQuery) use ($branchId) {
                    $scopeQuery
                        ->where(function ($libraryScopeQuery) {
                            $libraryScopeQuery
                                ->where('scope', Reservation::SCOPE_LIBRARY)
                                ->whereNull('branch_id');
                        })
                        ->orWhere(function ($branchScopeQuery) use ($branchId) {
                            if ($branchId === null) {
                                $branchScopeQuery->whereRaw('1 = 0');

                                return;
                            }

                            $branchScopeQuery
                                ->where('scope', Reservation::SCOPE_BRANCH)
                                ->where('branch_id', $branchId);
                        });
                });
            })
            ->with('user:id,name,email,membership_number')
            ->active()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('livewire.reservations.reservation-summary', [
            'activeCount' => $activeReservations->count(),
            'readyReservations' => $activeReservations->filter->isReady()->values(),
            'waitingReservations' => $activeReservations->filter->isPending()->values(),
        ]);
    }
}








