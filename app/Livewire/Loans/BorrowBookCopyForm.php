<?php

namespace App\Livewire\Loans;

use App\Actions\Loans\BorrowBookCopyAction;
use App\Models\BookCopy;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class BorrowBookCopyForm extends Component
{
    public BookCopy $bookCopy;

    public ?int $preferredReservationId = null;

    public ?Reservation $preferredReservation = null;

    public bool $compactPreferredActions = false;

    public bool $isOpen = false;

    public string $memberSearch = '';

    public ?int $selectedMemberId = null;

    public ?array $selectedMember = null;

    public ?string $dueAt = null;

    public bool $noDueDate = false;

    public string $notes = '';

    public bool $overrideReservation = false;

    public string $overrideReason = '';

    public function mount(BookCopy $bookCopy, ?int $preferredReservationId = null, bool $compactPreferredActions = false): void
    {
        $this->bookCopy = $bookCopy->loadMissing(['book:id,slug,title', 'library:id,name']);
        $this->preferredReservationId = $preferredReservationId;
        $this->compactPreferredActions = $compactPreferredActions;
        $this->dueAt = now()->addDays(14)->toDateString();
        $this->preferredReservation = $this->resolvePreferredReservation();
    }

    public function open(): void
    {
        if (! $this->canBorrow()) {
            return;
        }

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->resetErrorBag();
    }

    public function updatedNoDueDate(bool $value): void
    {
        if ($value) {
            $this->dueAt = null;
        } elseif (! $this->dueAt) {
            $this->dueAt = now()->addDays(14)->toDateString();
        }
    }

    public function selectMember(int $memberId): void
    {
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        $member = User::query()
            ->with('libraryMemberships.library:id,name,code')
            ->whereKey($memberId)
            ->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('library_id', $this->bookCopy->library_id)
                ->where('is_active', true))
            ->where('role', 'narys')
            ->where('is_active', true)
            ->first();

        if (! $member) {
            $this->addError('selectedMemberId', 'Narys nerastas šioje bibliotekoje.');

            return;
        }

        $this->selectedMemberId = $member->id;
        $this->selectedMember = [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'membership_number' => $member->membership_number,
            'phone' => $member->phone,
            'library_name' => $member->library?->name,
        ];
        $this->memberSearch = '';
        $this->overrideReservation = false;
        $this->overrideReason = '';
        $this->resetErrorBag(['selectedMemberId', 'overrideReservation', 'overrideReason']);
    }

    public function clearMember(): void
    {
        $this->selectedMemberId = null;
        $this->selectedMember = null;
        $this->overrideReservation = false;
        $this->overrideReason = '';
    }

    public function issuePreferred()
    {
        $actor = Auth::user();

        if (! $actor || ! $this->canIssuePreferred()) {
            abort(403);
        }

        Gate::authorize('borrow', $this->bookCopy);

        try {
            app(BorrowBookCopyAction::class)->handle($actor, $this->bookCopy->fresh(), [
                'user_id' => $this->preferredReservation->user_id,
                'due_at' => $this->preferredReservation->expires_at?->toDateString(),
                'no_due_date' => false,
                'notes' => 'Išduota pagal aktyvią rezervaciją.',
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($this->mapActionField($field), $messages[0] ?? 'Nepavyko išduoti kopijos.');
            }

            return null;
        }

        return redirect()
            ->route('books.show', $this->bookCopy->book)
            ->with('success', 'Kopija sėkmingai išduota rezervavusiam nariui.');
    }

    public function save()
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        Gate::authorize('borrow', $this->bookCopy);

        $this->validate([
            'selectedMemberId' => ['required', 'integer'],
            'dueAt' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'noDueDate' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'overrideReservation' => ['boolean'],
            'overrideReason' => ['nullable', 'string', 'max:1000'],
        ], [
            'selectedMemberId.required' => 'Pasirinkite narį.',
            'dueAt.date_format' => 'Data turi būti formato YYYY-MM-DD.',
            'dueAt.after' => 'Grąžinimo data turi būti vėlesnė nei šiandien.',
            'notes.max' => 'Pastabos negali virsyti 1000 simboliu.',
            'overrideReason.max' => 'Komentaras negali virsyti 1000 simboliu.',
        ]);

        if ($this->noDueDate && $this->dueAt) {
            $this->addError('dueAt', 'Negalima vienu metu nurodyti datos ir pasirinkti "be termino".');

            return null;
        }

        try {
            app(BorrowBookCopyAction::class)->handle($actor, $this->bookCopy->fresh(), [
                'user_id' => $this->selectedMemberId,
                'due_at' => $this->dueAt,
                'no_due_date' => $this->noDueDate,
                'notes' => $this->notes,
                'override_reservation' => $this->overrideReservation,
                'override_reason' => $this->overrideReason,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($this->mapActionField($field), $messages[0] ?? 'Nepavyko išduoti kopijos.');
            }

            return null;
        }

        return redirect()
            ->route('books.show', $this->bookCopy->book)
            ->with('success', 'Kopija sėkmingai išduota.');
    }

    public function render(SearchLibraryMembersQuery $searchLibraryMembersQuery)
    {
        $actor = Auth::user();
        $members = collect();

        if ($actor && $this->isOpen && trim($this->memberSearch) !== '') {
            $members = $searchLibraryMembersQuery->handle($actor, $this->memberSearch);
        }

        return view('livewire.loans.borrow-book-copy-form', [
            'members' => $members,
            'canBorrow' => $this->canBorrow(),
            'canIssuePreferred' => $this->canIssuePreferred(),
            'borrowUnavailableTitle' => $this->borrowUnavailableTitle(),
            'issueLibraryName' => $this->bookCopy->library?->name,
        ]);
    }

    private function canBorrow(): bool
    {
        $actor = Auth::user();

        return $actor
            && $this->bookCopy->status === BookCopy::STATUS_AVAILABLE
            && $this->bookCopy->activeLoan === null
            && Gate::allows('borrow', $this->bookCopy);
    }

    private function borrowUnavailableTitle(): ?string
    {
        $actor = Auth::user();

        if (! $actor || $this->canBorrow()) {
            return null;
        }

        if (
            $actor->role === User::ROLE_STAFF
            && $actor->belongsToLibrary($this->bookCopy->library_id)
            && ! $actor->canManageBookCopy($this->bookCopy)
        ) {
            return 'Negalima išduoti: kopija priklauso kitam filialui.';
        }

        if ($this->bookCopy->activeLoan !== null) {
            return 'Negalima išduoti: kopija šiuo metu jau išduota.';
        }

        if ($this->bookCopy->status !== BookCopy::STATUS_AVAILABLE) {
            return match ($this->bookCopy->status) {
                BookCopy::STATUS_LOANED => 'Negalima išduoti: kopija šiuo metu jau išduota.',
                BookCopy::STATUS_LOST => 'Negalima išduoti: kopija pažymėta kaip prarasta.',
                BookCopy::STATUS_DAMAGED => 'Negalima išduoti: kopija pažymėta kaip sugadinta.',
                BookCopy::STATUS_MAINTENANCE => 'Negalima išduoti: kopija šiuo metu tvarkoma.',
                BookCopy::STATUS_WITHDRAWN => 'Negalima išduoti: kopija nurašyta iš fondo.',
                default => 'Negalima išduoti: kopijos būsena neleidžia išdavimo.',
            };
        }

        if (! Gate::allows('borrow', $this->bookCopy)) {
            return 'Negalima išduoti: neturite teisės išduoti šios kopijos.';
        }

        return 'Negalima išduoti: kopija neatitinka išdavimo sąlygų.';
    }

    private function canIssuePreferred(): bool
    {
        return $this->canBorrow()
            && $this->preferredReservation?->isPending()
            && $this->preferredReservation?->user !== null;
    }

    private function resolvePreferredReservation(): ?Reservation
    {
        if (! $this->preferredReservationId) {
            return null;
        }

        return Reservation::query()
            ->with('user')
            ->whereKey($this->preferredReservationId)
            ->where('library_id', $this->bookCopy->library_id)
            ->where('book_id', $this->bookCopy->book_id)
            ->pending()
            ->first();
    }

    private function mapActionField(string $field): string
    {
        return match ($field) {
            'user_id' => 'selectedMemberId',
            'due_at' => 'dueAt',
            'book_copy' => 'bookCopy',
            'reservation_override' => 'overrideReservation',
            'override_reason' => 'overrideReason',
            default => $field,
        };
    }
}
