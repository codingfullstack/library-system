<?php

namespace App\Livewire\Manage\BookCopies;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CreateBookCopyPage extends Component
{
    use WithPagination;

    public string $search = '';

    public $selectedLibraryId = null;

    public ?int $selectedBookId = null;

    public function mount(): void
    {
        $actor = Auth::user();

        abort_unless($actor, 403);
        Gate::authorize('create', BookCopy::class);

        $this->search = trim((string) request()->query('search', ''));
        $this->selectedLibraryId = $actor->isSuperAdmin()
            ? request()->query('library_id')
            : $actor->activeLibraryId();
        $routeBook = request()->route('book');
        $this->selectedBookId = $routeBook instanceof Book
            ? $routeBook->id
            : (request()->integer('book_id') ?: null);
    }

    public function updatedSearch(): void
    {
        $this->selectedBookId = null;
        $this->resetPage();
    }

    public function updatedSelectedLibraryId(): void
    {
        $this->selectedBookId = null;
        $this->resetPage();
    }

    public function selectBook(int $bookId): void
    {
        $this->selectedBookId = $bookId;
    }

    #[On('book-copy-drawer-close')]
    public function closeDrawer(): void
    {
        $this->selectedBookId = null;
    }

    public function render()
    {
        $actor = Auth::user();
        $search = trim($this->search);

        $books = Book::query()
            ->with(['authors:id,name', 'publisher:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%")
                        ->orWhereHas('authors', fn ($authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('publisher', fn ($publisherQuery) => $publisherQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('title')
            ->paginate(10);

        return view('livewire.manage.book-copies.create-book-copy-page', [
            'books' => $books,
            'selectedBook' => $this->selectedBookId
                ? Book::query()->with(['authors:id,name', 'publisher:id,name', 'categories:id,name'])->find($this->selectedBookId)
                : null,
            'libraries' => $actor?->isSuperAdmin()
                ? Library::query()->orderBy('name')->get(['id', 'name'])
                : new Collection(),
        ]);
    }
}
