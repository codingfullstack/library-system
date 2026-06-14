<?php

namespace App\Livewire\Reservations;

use App\Actions\Reservations\CreateReservationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Users\SearchLibraryMembersQuery;
use App\Services\ReservationQueueService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class CreateReservationForm extends Component
{
    public int $bookId;

    public string $bookTitle = '';

    public string $memberSearch = '';

    public ?int $selectedMemberId = null;

    public ?array $selectedMember = null;

    public string $scope = Reservation::SCOPE_LIBRARY;

    public int|string|null $branchId = null;

    public ?string $expiresAt = null;

    public string $notes = '';

    public ?string $successMessage = null;

    public function mount(Book $book): void
    {
        $this->bookId = $book->id;
        $this->bookTitle = $book->title;

        $actor = Auth::user();

        if ($actor?->role === User::ROLE_STAFF && $actor->assignedBranchId()) {
            $this->scope = Reservation::SCOPE_BRANCH;
            $this->branchId = $actor->assignedBranchId();
        }
    }

    public function updatedScope(): void
    {
        $actor = Auth::user();

        if ($this->scope === Reservation::SCOPE_LIBRARY) {
            $this->branchId = null;

            return;
        }

        if ($actor?->role === User::ROLE_STAFF) {
            $this->branchId = $actor->assignedBranchId($actor->activeLibraryId());
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
            ->when(! $actor->isSuperAdmin(), function ($query) use ($actor) {
                $libraryId = $actor->activeLibraryId();

                $query->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                    ->where('library_id', $libraryId)
                    ->where('is_active', true));
            })
            ->where('role', User::ROLE_MEMBER)
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
            'selectedMemberId.required' => 'Pasirinkite nari.',
            'scope.required' => 'Pasirinkite rezervacijos apimti.',
            'scope.in' => 'Pasirinkite galiojancia rezervacijos apimti.',
            'branchId.required_if' => 'Pasirinkite filiala.',
            'expiresAt.after' => 'Galiojimo data turi buti ateityje.',
            'notes.max' => 'Pastabos negali virsyti 1000 simboliu.',
        ]);

        try {
            app(CreateReservationAction::class)->handle($actor, [
                'book_id' => $this->bookId,
                'user_id' => $this->selectedMemberId,
                'scope' => $this->scope,
                'branch_id' => $this->selectedScopeBranchId($actor),
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
        $this->successMessage = 'Rezervacija sekmingai sukurta.';

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
            'branchOptions' => $this->branchOptions($actor),
            'staffBranchName' => $this->staffBranchName($actor),
        ]);
    }

    private function rulesFor(User $actor): array
    {
        $rules = [
            'scope' => ['required', Rule::in([Reservation::SCOPE_BRANCH, Reservation::SCOPE_LIBRARY])],
            'branchId' => ['required_if:scope,'.Reservation::SCOPE_BRANCH, 'nullable', 'integer'],
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
        return $actor->hasAnyEffectiveRole([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_STAFF]);
    }

    private function hasQueueAhead(): bool
    {
        $actor = Auth::user();
        $libraryId = $actor ? $this->selectedReservationLibraryId($actor) : null;

        if (! $actor || ! $libraryId) {
            return false;
        }

        return app(ReservationQueueService::class)
            ->pendingReservationsQuery($libraryId, $this->bookId, $this->scope, $this->selectedScopeBranchId($actor))
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
            'branch_id' => 'branchId',
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

        $branchId = $this->selectedScopeBranchId($actor);

        $hasCopies = BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $this->bookId)
            ->when($this->scope === Reservation::SCOPE_BRANCH, fn ($query) => $query->where('branch_id', $branchId))
            ->exists();

        if (! $hasCopies) {
            return false;
        }

        return ! app(ReservationQueueService::class)
            ->hasAvailableCopies($libraryId, $this->bookId, $this->scope, $branchId);
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

        $branchId = $this->selectedScopeBranchId($actor);
        $scopeLabel = $this->scope === Reservation::SCOPE_BRANCH ? 'pasirinktame filiale' : 'tavo bibliotekose';

        $hasCopies = BookCopy::query()
            ->withoutGlobalScope('library')
            ->where('library_id', $libraryId)
            ->where('book_id', $this->bookId)
            ->when($this->scope === Reservation::SCOPE_BRANCH, fn ($query) => $query->where('branch_id', $branchId))
            ->exists();

        if (! $hasCopies) {
            return 'Si knyga '.$scopeLabel.' neprieinama.';
        }

        if (app(ReservationQueueService::class)->hasAvailableCopies($libraryId, $this->bookId, $this->scope, $branchId)) {
            return 'Si knyga siuo metu prieinama '.$scopeLabel.', rezervacija nereikalinga.';
        }

        return null;
    }

    private function selectedScopeBranchId(?User $actor): ?int
    {
        if ($this->scope !== Reservation::SCOPE_BRANCH) {
            return null;
        }

        if ($actor?->role === User::ROLE_STAFF) {
            return $actor->assignedBranchId($actor->activeLibraryId());
        }

        return $this->branchId ? (int) $this->branchId : null;
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branchOptions(?User $actor): Collection
    {
        if (! $actor) {
            return collect();
        }

        $libraryId = $this->selectedReservationLibraryId($actor);

        if (! $libraryId) {
            return collect();
        }

        return Branch::query()
            ->where('library_id', $libraryId)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function staffBranchName(?User $actor): ?string
    {
        if ($actor?->role !== User::ROLE_STAFF) {
            return null;
        }

        $branchId = $actor->assignedBranchId($actor->activeLibraryId());

        if (! $branchId) {
            return null;
        }

        return Branch::query()->whereKey($branchId)->value('name');
    }
}
