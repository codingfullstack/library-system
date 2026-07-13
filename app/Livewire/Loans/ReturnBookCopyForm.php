<?php

namespace App\Livewire\Loans;

use App\Actions\Loans\ReturnBookCopyAction;
use App\Models\BookCopy;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ReturnBookCopyForm extends Component
{
    public BookCopy $bookCopy;

    public bool $confirming = false;

    public function mount(BookCopy $bookCopy): void
    {
        $this->bookCopy = $bookCopy->loadMissing('book:id,slug,title');
    }

    public function confirm(): void
    {
        if (! $this->canReturn()) {
            return;
        }

        $this->confirming = true;
    }

    public function cancel(): void
    {
        $this->confirming = false;
        $this->resetErrorBag();
    }

    public function save()
    {
        $actor = Auth::user();

        if (! $actor) {
            abort(403);
        }

        Gate::authorize('return', $this->bookCopy);

        try {
            app(ReturnBookCopyAction::class)->handle($actor, $this->bookCopy->fresh());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($this->mapActionField($field), $messages[0] ?? 'Nepavyko grąžinti kopijos.');
            }

            return null;
        }

        return redirect()
            ->route('books.show', $this->bookCopy->book)
            ->with('success', 'Kopija sėkmingai grąžinta.');
    }

    public function render()
    {
        return view('livewire.loans.return-book-copy-form', [
            'canReturn' => $this->canReturn(),
            'returnUnavailableTitle' => $this->returnUnavailableTitle(),
        ]);
    }

    private function canReturn(): bool
    {
        $actor = Auth::user();

        return $actor
            && $this->bookCopy->activeLoan !== null
            && Gate::allows('return', $this->bookCopy);
    }

    private function returnUnavailableTitle(): ?string
    {
        $actor = Auth::user();

        if (! $actor || $this->canReturn()) {
            return null;
        }

        if (
            $actor->role === User::ROLE_STAFF
            && $actor->belongsToLibrary($this->bookCopy->library_id)
            && ! $actor->canManageBookCopy($this->bookCopy)
        ) {
            return 'Negalima priimti grąžinimo: kopija priklauso kitam filialui.';
        }

        return null;
    }

    private function mapActionField(string $field): string
    {
        return match ($field) {
            'book_copy' => 'bookCopy',
            default => $field,
        };
    }
}
