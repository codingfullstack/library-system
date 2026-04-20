<?php

namespace App\Actions\Loans;

use App\Models\BookCopy;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReturnBookCopyAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $authUser, BookCopy $bookCopy): array
    {
        $activeLoan = $bookCopy->activeLoan()->first();

        if (! $activeLoan) {
            throw ValidationException::withMessages([
                'book_copy' => ['Aktyvi paskola nerasta.'],
            ]);
        }

        $activeLoan->update([
            'status'      => 'returned',
            'returned_at' => now(),
            'received_by' => $authUser->id,
        ]);

        $bookCopy->update([
            'status' => 'available',
        ]);

        return [
            'message' => 'Kopija sėkmingai grąžinta.',
        ];
    }
}