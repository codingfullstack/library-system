<?php

namespace App\Actions\Loans;

use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BorrowBookCopyAction
{
    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function handle(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        if ($bookCopy->status !== 'available') {
            throw ValidationException::withMessages([
                'book_copy' => ['Šios kopijos išduoti negalima.'],
            ]);
        }

        $member = User::query()
            ->where('id', $validated['user_id'])
            ->where('library_id', $bookCopy->library_id)
            ->where('role', 'member')
            ->where('is_active', true)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['Narys nerastas šioje bibliotekoje.'],
            ]);
        }

        $dueAt = null;

        if (! ($validated['no_due_date'] ?? false)) {
            $dueAt = ! empty($validated['due_at'])
                ? $validated['due_at']
                : now()->addDays(14)->format('Y-m-d');
        }

        $loan = Loan::create([
            'library_id'   => $bookCopy->library_id,
            'book_copy_id' => $bookCopy->id,
            'user_id'      => $member->id,
            'issued_by'    => $authUser->id,
            'borrowed_at'  => now(),
            'due_at'       => $dueAt,
            'status'       => 'active',
            'notes'        => $validated['notes'] ?? null,
        ]);

        $bookCopy->update([
            'status' => 'loaned',
        ]);

        return [
            'message' => 'Kopija sėkmingai išduota.',
            'loan' => $loan,
        ];
    }
}