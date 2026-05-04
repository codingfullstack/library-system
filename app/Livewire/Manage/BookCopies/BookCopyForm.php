<?php

namespace App\Livewire\Manage\BookCopies;

use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class BookCopyForm extends Component
{
    public ?BookCopy $bookCopy = null;

    public ?Book $selectedBook = null;

    public bool $isEditing = false;

    public $selectedLibraryId = null;

    public $branchId = null;

    public $locationId = null;

    public string $inventoryCode = '';

    public string $barcode = '';

    public string $status = BookCopy::STATUS_AVAILABLE;

    public string $conditionStatus = 'good';

    public string $acquiredAt = '';

    public string $notes = '';

    public function mount(?Book $selectedBook = null, ?BookCopy $bookCopy = null, $selectedLibraryId = null): void
    {
        $actor = Auth::user();

        abort_unless($actor, 403);

        if ($bookCopy) {
            Gate::authorize('update', $bookCopy);

            $bookCopy->loadMissing(['book.authors:id,name', 'book.publisher:id,name', 'book.categories:id,name']);

            $this->bookCopy = $bookCopy;
            $this->selectedBook = $bookCopy->book;
            $this->isEditing = true;
            $this->selectedLibraryId = $bookCopy->library_id;
            $this->branchId = $bookCopy->branch_id;
            $this->locationId = $bookCopy->location_id;
            $this->inventoryCode = (string) $bookCopy->inventory_code;
            $this->barcode = (string) ($bookCopy->barcode ?? '');
            $this->status = (string) ($bookCopy->status ?: BookCopy::STATUS_AVAILABLE);
            $this->conditionStatus = (string) ($bookCopy->condition_status ?: 'good');
            $this->acquiredAt = $bookCopy->acquired_at?->format('Y-m-d') ?? '';
            $this->notes = (string) ($bookCopy->notes ?? '');

            return;
        }

        Gate::authorize('create', BookCopy::class);

        if ($selectedBook) {
            $selectedBook->loadMissing(['authors:id,name', 'publisher:id,name', 'categories:id,name']);
        }

        $this->selectedBook = $selectedBook;
        $this->selectedLibraryId = $actor->isSuperAdmin()
            ? $selectedLibraryId
            : $actor->library_id;
    }

    public function updatedSelectedLibraryId(): void
    {
        $this->branchId = null;
        $this->locationId = null;
        $this->resetErrorBag(['branchId', 'locationId']);
    }

    public function updatedBranchId(): void
    {
        $this->locationId = null;
        $this->resetErrorBag('locationId');
    }

    public function save()
    {
        $actor = Auth::user();

        abort_unless($actor, 403);

        if ($this->isEditing && $this->bookCopy) {
            Gate::authorize('update', $this->bookCopy);
        } else {
            Gate::authorize('create', BookCopy::class);
        }

        if (! $this->selectedBook) {
            throw ValidationException::withMessages([
                'selectedBook' => 'Pirma pasirink knyga.',
            ]);
        }

        $libraryId = $actor->isSuperAdmin()
            ? $this->selectedLibraryId
            : $actor->library_id;

        $bookCopyId = $this->bookCopy?->id;

        $validated = $this->validate([
            'selectedLibraryId' => [
                Rule::requiredIf(fn () => $actor->isSuperAdmin()),
                'nullable',
                'integer',
                'exists:libraries,id',
            ],
            'branchId' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('library_id', $libraryId)),
            ],
            'locationId' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('library_id', $libraryId)),
            ],
            'inventoryCode' => [
                'required',
                'string',
                'max:255',
                Rule::unique('book_copies', 'inventory_code')
                    ->where(fn ($query) => $query->where('library_id', $libraryId))
                    ->ignore($bookCopyId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('book_copies', 'barcode')
                    ->where(fn ($query) => $query->where('library_id', $libraryId))
                    ->ignore($bookCopyId),
            ],
            'status' => ['required', Rule::in(array_keys($this->isEditing ? $this->statusOptions() : $this->creatableStatusOptions()))],
            'conditionStatus' => ['required', Rule::in(array_keys($this->conditionOptions()))],
            'acquiredAt' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [], [
            'selectedLibraryId' => 'biblioteka',
            'branchId' => 'filialas',
            'locationId' => 'vieta',
            'inventoryCode' => 'inventoriaus kodas',
            'barcode' => 'bruksninis kodas',
            'status' => 'pradine busena',
            'conditionStatus' => 'fizine bukle',
            'acquiredAt' => 'isigijimo data',
            'notes' => 'pastabos',
        ]);

        if ($validated['locationId']) {
            $location = Location::query()->find($validated['locationId']);

            if (! $location || (int) $location->branch_id !== (int) $validated['branchId']) {
                $this->addError('locationId', 'Pasirinkta vieta nepriklauso siam filialui.');

                return null;
            }
        }

        $payload = [
            'library_id' => (int) $libraryId,
            'book_id' => $this->selectedBook->id,
            'branch_id' => (int) $validated['branchId'],
            'location_id' => $validated['locationId'] ? (int) $validated['locationId'] : null,
            'inventory_code' => $validated['inventoryCode'],
            'barcode' => $validated['barcode'] ?: null,
            'condition_status' => $validated['conditionStatus'],
            'acquired_at' => $validated['acquiredAt'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ];

        if ($this->bookCopy) {
            $this->bookCopy->update($payload);

            return redirect()
                ->route('book-copies.show', $this->bookCopy)
                ->with('success', 'Egzempliorius atnaujintas.');
        }

        $payload['qr_code'] = $this->generateQrCode((int) $libraryId);
        $payload['status'] = $validated['status'];

        $copy = BookCopy::create($payload);

        app(ChangeBookCopyStatusAction::class)->handle(
            $copy,
            $validated['status'],
            $actor,
            'created',
            $validated['notes'] ?: 'Egzempliorius sukurtas sistemoje.'
        );

        return redirect()
            ->route('book-copies.show', $copy)
            ->with('success', 'Egzempliorius sekmingai pridetas prie esamos knygos.');
    }

    public function render()
    {
        $actor = Auth::user();
        $libraryId = $actor?->isSuperAdmin()
            ? $this->selectedLibraryId
            : $actor?->library_id;

        $libraries = $actor?->isSuperAdmin()
            ? Library::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        $branches = Branch::query()
            ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
            ->when(! $actor?->isSuperAdmin(), fn ($query) => $query->where('library_id', $actor?->library_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $locations = Location::query()
            ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
            ->when($this->branchId, fn ($query) => $query->where('branch_id', $this->branchId))
            ->when(! $actor?->isSuperAdmin(), fn ($query) => $query->where('library_id', $actor?->library_id))
            ->with('branch:id,name')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'room', 'shelf']);

        return view('livewire.manage.book-copies.book-copy-form', [
            'libraries' => $libraries,
            'branches' => $branches,
            'locations' => $locations,
            'statusOptions' => $this->statusOptions(),
            'creatableStatusOptions' => $this->creatableStatusOptions(),
            'conditionOptions' => $this->conditionOptions(),
        ]);
    }

    private function statusOptions(): array
    {
        return BookCopy::statusLabels();
    }

    private function creatableStatusOptions(): array
    {
        return [
            BookCopy::STATUS_AVAILABLE => 'Laisva',
            BookCopy::STATUS_DAMAGED => 'Sugadinta',
            BookCopy::STATUS_MAINTENANCE => 'Tvarkoma',
            BookCopy::STATUS_LOST => 'Prarasta',
            BookCopy::STATUS_WITHDRAWN => 'Nurasytas fondas',
        ];
    }

    private function conditionOptions(): array
    {
        return [
            'new' => 'Nauja',
            'good' => 'Gera',
            'worn' => 'Padeveta',
            'damaged' => 'Pazeista',
        ];
    }

    private function generateQrCode(int $libraryId): string
    {
        do {
            $candidate = 'QR-' . $libraryId . '-' . strtoupper(Str::random(12));
        } while (
            BookCopy::query()
                ->where('library_id', $libraryId)
                ->where('qr_code', $candidate)
                ->exists()
        );

        return $candidate;
    }
}
