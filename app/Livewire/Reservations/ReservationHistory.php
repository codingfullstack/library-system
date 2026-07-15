<?php

namespace App\Livewire\Reservations;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationQueueService;
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
        return $this->issueFirstInQueue();
    }

    public function issueFirstInQueue(): mixed
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        $bookCopy = $this->firstAvailableCopy();

        if (! $bookCopy) {
            $this->addError('reservation', 'Nėra laisvos kopijos, kurią galėtumėte išduoti.');

            return null;
        }

        Gate::authorize('borrow', $bookCopy);

        $reservation = $this->currentReservation($bookCopy);

        if (! $reservation) {
            $this->addError('reservation', 'Nėra rezervacijos, kurią būtų galima išduoti šiai kopijai.');

            return null;
        }

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
        $borrowableCopy = $this->firstAvailableCopy();
        $currentReservationId = $this->currentReservation($borrowableCopy)?->id
            ?? $reservations->getCollection()->first(fn (Reservation $reservation) => $reservation->isPending())?->id;
        $canManage = $this->canManage();
        $canIssueCurrent = $borrowableCopy !== null && $currentReservationId !== null && $canManage;

        return view('livewire.reservations.reservation-history', [
            'reservations' => $reservations,
            'currentReservationId' => $currentReservationId,
            'canManage' => $canManage,
            'canIssueCurrent' => $canIssueCurrent,
            'unavailableIssueMessage' => $canManage && $borrowableCopy === null
                ? $this->unavailableIssueMessage()
                : null,
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
            ->when($actor->role === User::ROLE_STAFF, function ($query) use ($actor) {
                $this->scopeReservationsToStaffBranch($query, $actor);
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
            return [$left->created_at, $left->id] <=> [$right->created_at, $right->id];
        }

        return [$right->created_at, $right->id] <=> [$left->created_at, $left->id];
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

    private function currentReservation(?BookCopy $bookCopy = null): ?Reservation
    {
        $actor = Auth::user();

        if (! $actor) {
            return null;
        }

        $query = $bookCopy
            ? app(ReservationQueueService::class)
                ->serviceablePendingReservationsQuery($bookCopy->library_id, $bookCopy->book_id, (int) $bookCopy->branch_id)
            : Reservation::query()
                ->where('book_id', $this->bookId);

        return $query
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $query->where('library_id', $actor->activeLibraryId());
            })
            ->when($actor->role === User::ROLE_STAFF, function ($query) use ($actor) {
                $this->scopeReservationsToStaffBranch($query, $actor);
            })
            ->with('user:id,name,email,membership_number')
            ->pending()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    private function firstAvailableCopy(): ?BookCopy
    {
        $actor = Auth::user();

        if (! $actor) {
            return null;
        }

        $activeLibraryId = $actor->activeLibraryId();

        if (! $activeLibraryId) {
            return null;
        }

        return BookCopy::query()
            ->where('book_id', $this->bookId)
            ->where('library_id', $activeLibraryId)
            ->where('status', BookCopy::STATUS_AVAILABLE)
            ->when($actor->role === User::ROLE_STAFF, function ($query) use ($actor, $activeLibraryId) {
                $branchId = $actor->assignedBranchId($activeLibraryId);

                if ($branchId === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where('branch_id', $branchId);
            })
            ->orderBy('inventory_code')
            ->orderBy('id')
            ->get()
            ->first(fn (BookCopy $bookCopy) => Gate::allows('borrow', $bookCopy));
    }

    private function unavailableIssueMessage(): string
    {
        $actor = Auth::user();

        if ($actor?->role === User::ROLE_STAFF) {
            return 'Jūsų filiale nėra laisvos kopijos išdavimui.';
        }

        return 'Šioje bibliotekoje nėra laisvos kopijos išdavimui.';
    }

    private function scopeReservationsToStaffBranch($query, User $actor): void
    {
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
    }
}
