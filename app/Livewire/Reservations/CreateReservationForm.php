<?php

namespace App\Livewire\Reservations;

use App\Actions\Reservations\CreateReservationAction;
use App\Models\Book;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Users\SearchLibraryMembersQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CreateReservationForm extends Component
{
    public int $bookId;

    public string $bookTitle = '';

    public string $memberSearch = '';

    public ?int $selectedMemberId = null;

    public ?array $selectedMember = null;

    public ?string $expiresAt = null;

    public string $notes = '';

    public ?string $successMessage = null;

    public function mount(Book $book): void
    {
        $this->bookId = $book->id;
        $this->bookTitle = $book->title;
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
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $libraryId = $actor->activeLibraryId();

                $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true));
            })
            ->where('role', 'narys')
            ->where('is_active', true)
            ->first();

        if (! $member) {
            $this->addError('selectedMemberId', 'Narys nerastas.');

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
        $this->resetErrorBag('selectedMemberId');
    }

    public function clearMember(): void
    {
        $this->selectedMemberId = null;
        $this->selectedMember = null;
    }

    public function save()
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        $this->validate($this->rulesFor($actor), [
            'selectedMemberId.required' => 'Pasirinkite narį.',
            'expiresAt.after' => 'Galiojimo data turi būti ateityje.',
            'notes.max' => 'Pastabos negali virsyti 1000 simboliu.',
        ]);

        try {
            app(CreateReservationAction::class)->handle($actor, [
                'book_id' => $this->bookId,
                'user_id' => $this->selectedMemberId,
                'expires_at' => $this->hasQueueAhead() ? null : $this->expiresAt,
                'notes' => $this->notes,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($this->mapActionField($field), $messages[0] ?? 'Nepavyko sukurti rezervacijos.');
            }

            return null;
        }

        $this->clearMember();
        $this->expiresAt = null;
        $this->notes = '';
        $this->successMessage = 'Rezervacija sėkmingai sukurta.';

        $this->dispatch('reservation-created', bookId: $this->bookId);

        return null;
    }

    public function render(SearchLibraryMembersQuery $searchLibraryMembersQuery)
    {
        $actor = Auth::user();
        $members = collect();
        $isReservable = $this->isReservable($actor);
        $reservationBlockedMessage = $this->reservationBlockedMessage($actor);

        if ($actor && $isReservable && $this->usesMemberSearch($actor) && trim($this->memberSearch) !== '') {
            $members = $searchLibraryMembersQuery->handle($actor, $this->memberSearch);
        }

        return view('livewire.reservations.create-reservation-form', [
            'actor' => $actor,
            'members' => $members,
            'isReservable' => $isReservable,
            'reservationBlockedMessage' => $reservationBlockedMessage,
            'usesMemberSearch' => $actor ? $this->usesMemberSearch($actor) : false,
            'hasQueueAhead' => $this->hasQueueAhead(),
            'selectedLibraryName' => $this->selectedLibraryName($actor),
        ]);
    }

    private function rulesFor(User $actor): array
    {
        $rules = [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->usesMemberSearch($actor)) {
            $rules['selectedMemberId'] = ['required', 'integer'];
            $rules['expiresAt'] = ['nullable', 'date', 'after:now'];
        }

        return $rules;
    }

    private function usesMemberSearch(User $actor): bool
    {
        return $actor->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas']);
    }

    private function hasQueueAhead(): bool
    {
        $actor = Auth::user();
        $libraryId = $actor ? $this->selectedReservationLibraryId($actor) : null;

        if (! $libraryId) {
            return false;
        }

        return Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $this->bookId)
            ->pending()
            ->exists();
    }

    private function selectedReservationLibraryId(User $actor): ?int
    {
        if ($actor->isSuperAdmin()) {
            if (! $this->selectedMemberId) {
                return null;
            }

            $activeLibraryId = $actor->activeLibraryId();

            $member = User::query()
                ->whereKey($this->selectedMemberId)
                ->when($activeLibraryId, function ($query) use ($activeLibraryId) {
                    $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                        ->where('library_id', $activeLibraryId)
                        ->where('is_active', true));
                })
                ->first();

            if (! $member) {
                return $activeLibraryId;
            }

            return $member->activeLibraryMemberships()
                ->when($activeLibraryId, fn ($query) => $query->where('library_id', $activeLibraryId))
                ->orderBy('joined_at')
                ->orderBy('id')
                ->value('library_id') ?: $activeLibraryId;
        }

        return $actor->activeLibraryId();
    }

    private function selectedLibraryName(?User $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        if ($actor->isSuperAdmin()) {
            return $this->selectedMember['library_name'] ?? null;
        }

        return $actor->availableLibraries()->firstWhere('id', $actor->activeLibraryId())?->name;
    }

    private function mapActionField(string $field): string
    {
        return match ($field) {
            'user_id' => 'selectedMemberId',
            'expires_at' => 'expiresAt',
            default => $field,
        };
    }

    private function isReservable(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        if (! $this->isReservationAvailabilityKnown($actor)) {
            return true;
        }

        $libraryId = $this->selectedReservationLibraryId($actor);

        if (! $libraryId) {
            return false;
        }

        return Book::query()
            ->whereKey($this->bookId)
            ->whereHas('bookCopies', function ($query) use ($libraryId) {
                $query
                    ->where('library_id', $libraryId)
                    ->where('status', '!=', 'laisva');
            })
            ->whereDoesntHave('bookCopies', function ($query) use ($libraryId) {
                $query
                    ->where('library_id', $libraryId)
                    ->where('status', 'laisva');
            })
            ->exists();
    }

    private function isReservationAvailabilityKnown(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return $this->selectedMemberId !== null;
        }

        return true;
    }

    private function reservationBlockedMessage(?User $actor): ?string
    {
        if (! $actor || ! $this->isReservationAvailabilityKnown($actor)) {
            return null;
        }

        $libraryId = $this->selectedReservationLibraryId($actor);

        if (! $libraryId) {
            return null;
        }

        $hasCopies = Book::query()
            ->whereKey($this->bookId)
            ->whereHas('bookCopies', fn ($query) => $query->where('library_id', $libraryId))
            ->exists();

        if (! $hasCopies) {
            return 'Ši knyga tavo bibliotekose neprieinama.';
        }

        $hasAvailableCopy = Book::query()
            ->whereKey($this->bookId)
            ->whereHas('bookCopies', function ($query) use ($libraryId) {
                $query
                    ->where('library_id', $libraryId)
                    ->where('status', 'laisva');
            })
            ->exists();

        if ($hasAvailableCopy) {
            return 'Ši knyga šiuo metu prieinama tavo bibliotekose, rezervacija nereikalinga.';
        }

        return null;
    }
}








