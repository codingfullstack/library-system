<?php

namespace App\Livewire\Manage\BookCopies;

use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class BookCopyCreateDrawer extends Component
{
    public Book $book;

    public bool $isOpen = false;

    public $selectedLibraryId = null;

    public function mount(Book $book): void
    {
        $actor = Auth::user();

        abort_unless($actor, 403);
        Gate::authorize('create', BookCopy::class);

        $this->book = $book;
        $this->selectedLibraryId = $actor->isSuperAdmin()
            ? request()->query('library_id')
            : $actor->activeLibraryId();
    }

    #[On('open-book-copy-create-drawer')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    #[On('book-copy-drawer-close')]
    public function close(): void
    {
        $this->isOpen = false;
    }

    public function render()
    {
        return view('livewire.manage.book-copies.book-copy-create-drawer');
    }
}
