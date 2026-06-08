<?php

namespace App\Livewire\Reservations;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Reservation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ReservationHistory extends Component
{
    use WithPagination;

    public int $bookId;

    public ?string $message = null;

    public function mount(Book $book): void
    {
        $this->bookId = $book->id;
    }

    #[On('reservation-created')]
    #[On('reservation-updated')]
    public function refreshList(): void
    {
        $this->resetPage(pageName: 'reservation-history-page');
        $this->message = 'Rezervacijų sąrašas atnaujintas.';
    }

    public function cancel(int $reservationId): void
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        $reservation = Reservation::query()
            ->whereKey($reservationId)
            ->where('book_id', $this->bookId)
            ->firstOrFail();

        try {
            app(CancelReservationAction::class)->handle($actor, $reservation);
        } catch (ValidationException $exception) {
            $this->addError('reservation', $exception->errors()['reservation'][0] ?? 'Nepavyko atšaukti rezervacijos.');

            return;
        }

        $this->resetPage(pageName: 'reservation-history-page');
        $this->message = 'Rezervacija atšaukta.';
        $this->dispatch('reservation-updated', bookId: $this->bookId);
    }

    public function issueCurrent(): mixed
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        $reservation = $this->currentReservation();
        $bookCopy = $this->firstAvailableCopy();

        if (! $reservation || ! $bookCopy) {
            $this->addError('reservation', 'Nėra rezervacijos, kuria butu galima išduoti.');

            return null;
        }

        Gate::authorize('update', $bookCopy);

        try {
            app(BorrowBookCopyAction::class)->handle($actor, $bookCopy, [
                'user_id' => $reservation->user_id,
                'due_at' => null,
                'no_due_date' => false,
                'notes' => 'Išduota pagal rezervaciją.',
            ]);
        } catch (ValidationException $exception) {
            $this->addError('reservation', $exception->errors()['book_copy'][0] ?? $exception->errors()['user_id'][0] ?? 'Nepavyko išduoti rezervacijos.');

            return null;
        }

        return redirect()
            ->route('books.show', Book::query()->findOrFail($this->bookId))
            ->with('success', 'Kopija išduota pirmam eilėje esančiam nariui.');
    }

    public function render()
    {
        $reservations = $this->reservations();
        $currentReservationId = $reservations->getCollection()->first(fn (Reservation $reservation) => $reservation->isPending())?->id;
        $borrowableCopy = $this->firstBorrowableCopy();

        return view('livewire.reservations.reservation-history', [
            'reservations' => $reservations,
            'currentReservationId' => $currentReservationId,
            'canManage' => $this->canManage(),
            'canIssueCurrent' => $borrowableCopy !== null && $this->canManage(),
        ]);
    }

    private function reservations(): LengthAwarePaginator
    {
        $actor = Auth::user();

        if (! $actor) {
            return new LengthAwarePaginator([], 0, 8, 1, [
                'path' => request()->url(),
                'pageName' => 'reservation-history-page',
            ]);
        }

        $reservations = Reservation::query()
            ->where('book_id', $this->bookId)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->with('user:id,name,email,membership_number')
            ->get()
            ->sort(function (Reservation $left, Reservation $right) {
                return $this->compareReservations($left, $right);
            })
            ->values();

        $page = $this->getPage('reservation-history-page');
        $perPage = 8;
        $items = $reservations->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $reservations->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'reservation-history-page',
            ]
        );
    }

    private function compareReservations(Reservation $left, Reservation $right): int
    {
        $leftRank = $this->reservationRank($left);
        $rightRank = $this->reservationRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        if ($leftRank <= 1) {
            return $left->reserved_at <=> $right->reserved_at;
        }

        return $right->reserved_at <=> $left->reserved_at;
    }

    private function reservationRank(Reservation $reservation): int
    {
        if ($reservation->isPending()) {
            return 0;
        }

        return match ($reservation->status) {
            Reservation::STATUS_CANCELLED => 1,
            Reservation::STATUS_EXPIRED => 2,
            Reservation::STATUS_FULFILLED => 3,
            default => 4,
        };
    }

    private function canManage(): bool
    {
        $actor = Auth::user();

        return $actor && $actor->hasAnyEffectiveRole(['administratorius', 'darbuotojas']);
    }

    private function currentReservation(): ?Reservation
    {
        $actor = Auth::user();

        if (! $actor) {
            return null;
        }

        return Reservation::query()
            ->where('book_id', $this->bookId)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->with('user:id,name,email,membership_number')
            ->pending()
            ->orderBy('reserved_at')
            ->first();
    }

    private function firstBorrowableCopy(): ?BookCopy
    {
        $actor = Auth::user();

        if (! $actor) {
            return null;
        }

        return BookCopy::query()
            ->where('book_id', $this->bookId)
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->where('status', 'laisva')
            ->orderBy('inventory_code')
            ->orderBy('id')
            ->first();
    }
}
