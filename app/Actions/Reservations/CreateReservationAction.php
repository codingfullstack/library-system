<?php

namespace App\Actions\Reservations;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateReservationAction
{
    public function handle(User $actor, array $data): Reservation
    {
        $member = $this->resolveMember($actor, $data);

        $book = Book::query()
            ->whereKey($data['book_id'])
            ->firstOrFail();

        $belongsToLibrary = BookCopy::query()
            ->where('library_id', $actor->library_id)
            ->where('book_id', $book->id)
            ->exists();

        if (! $belongsToLibrary) {
            throw ValidationException::withMessages([
                'book_id' => 'Ši knyga nepriklauso jūsų bibliotekai.',
            ]);
        }

        $hasActiveLoan = Loan::query()
            ->where('library_id', $actor->library_id)
            ->where('user_id', $member->id)
            ->whereNull('returned_at')
            ->whereHas('bookCopy', function ($query) use ($book) {
                $query->where('book_id', $book->id);
            })
            ->exists();

        if ($hasActiveLoan) {
            throw ValidationException::withMessages([
                'book_id' => 'Šis narys jau turi aktyvią šios knygos paskolą.',
            ]);
        }

        $hasActiveReservation = Reservation::query()
            ->where('library_id', $actor->library_id)
            ->where('book_id', $book->id)
            ->where('user_id', $member->id)
            ->active()
            ->exists();

        if ($hasActiveReservation) {
            throw ValidationException::withMessages([
                'book_id' => 'Šis narys jau turi aktyvią šios knygos rezervaciją.',
            ]);
        }

        $hasAvailableCopy = BookCopy::query()
            ->where('library_id', $actor->library_id)
            ->where('book_id', $book->id)
            ->where('status', 'available')
            ->exists();

        if ($hasAvailableCopy) {
            throw ValidationException::withMessages([
                'book_id' => 'Knyga šiuo metu turi laisvą kopiją, rezervacija nereikalinga.',
            ]);
        }

        return Reservation::create([
            'library_id' => $actor->library_id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'status' => Reservation::STATUS_RESERVED,
            'reserved_at' => now(),
            'expires_at' => in_array($actor->role, ['admin', 'staff'], true)
                ? ($data['expires_at'] ?? null)
                : null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function resolveMember(User $actor, array $data): User
    {
        if ($actor->role === 'member') {
            if (! $actor->is_active) {
                throw ValidationException::withMessages([
                    'user' => 'Jūsų paskyra nėra aktyvi.',
                ]);
            }

            return $actor;
        }

        if (! in_array($actor->role, ['admin', 'staff'], true)) {
            throw ValidationException::withMessages([
                'user' => 'Neturite teisės kurti rezervacijos.',
            ]);
        }

        $member = User::query()
            ->whereKey($data['user_id'] ?? null)
            ->where('library_id', $actor->library_id)
            ->where('role', 'member')
            ->where('is_active', true)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => 'Narys nerastas šioje bibliotekoje.',
            ]);
        }

        return $member;
    }
}
