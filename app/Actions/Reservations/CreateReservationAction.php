<?php

namespace App\Actions\Reservations;

use App\Actions\AuditLogs\RecordAuditLogAction;
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
        $libraryId = $this->resolveLibraryId($actor, $member);

        $book = Book::query()
            ->whereKey($data['book_id'])
            ->firstOrFail();

        $belongsToLibrary = BookCopy::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->exists();

        if (! $belongsToLibrary) {
            throw ValidationException::withMessages([
                'book_id' => 'Ši knyga nepriklauso pasirinktai bibliotekai.',
            ]);
        }

        $hasActiveLoan = Loan::query()
            ->where('library_id', $libraryId)
            ->where('user_id', $member->id)
            ->whereNull('returned_at')
            ->whereHas('bookCopy', function ($query) use ($book) {
                $query->where('book_id', $book->id);
            })
            ->exists();

        if ($hasActiveLoan) {
            throw ValidationException::withMessages([
                'book_id' => 'Šis narys jau turi aktyviai išduotą šią knygą.',
            ]);
        }

        $hasPendingReservation = Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->where('user_id', $member->id)
            ->pending()
            ->exists();

        if ($hasPendingReservation) {
            throw ValidationException::withMessages([
                'book_id' => 'Šis narys jau turi laukiančią šios knygos rezervaciją.',
            ]);
        }

        $hasAvailableCopy = BookCopy::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->where('status', 'laisva')
            ->exists();

        if ($hasAvailableCopy) {
            throw ValidationException::withMessages([
                'book_id' => 'Knyga šiuo metu prieinama pasirinktoje bibliotekoje, rezervacija nereikalinga.',
            ]);
        }

        $hasQueueAhead = Reservation::query()
            ->where('library_id', $libraryId)
            ->where('book_id', $book->id)
            ->pending()
            ->exists();

        $reservation = Reservation::create([
            'library_id' => $libraryId,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'status' => Reservation::STATUS_RESERVED,
            'reserved_at' => now(),
            'expires_at' => $hasQueueAhead
                ? null
                : ($actor->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas']) ? ($data['expires_at'] ?? null) : null),
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => $data['notes'] ?? null,
        ]);

        app(SyncReservationQueueAction::class)->handle($libraryId, $book->id);

        app(RecordAuditLogAction::class)->handle(
            $actor,
            'reservation_created',
            $reservation,
            sprintf(
                'Sukurta rezervacija knygai "%s" nariui %s.',
                $book->title,
                $member->name
            ),
            [
                'reservation_id' => $reservation->id,
                'book_id' => $book->id,
                'book_title' => $book->title,
                'target_member_id' => $member->id,
                'target_member_name' => $member->name,
                'expires_at' => $reservation->expires_at?->toDateTimeString(),
            ],
            $libraryId
        );

        return $reservation->fresh();
    }

    private function resolveMember(User $actor, array $data): User
    {
        if ($actor->effectiveRole() === 'narys') {
            if (! $actor->is_active) {
                throw ValidationException::withMessages([
                    'user' => 'Jūsų paskyra nėra aktyvi.',
                ]);
            }

            return $actor;
        }

        if (! $actor->hasAnyEffectiveRole(['superadministratorius', 'administratorius', 'darbuotojas'])) {
            throw ValidationException::withMessages([
                'user' => 'Neturite teisės kurti rezervacijos.',
            ]);
        }

        $member = User::query()
            ->whereKey($data['user_id'] ?? null)
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
            throw ValidationException::withMessages([
                'user_id' => 'Narys nerastas.',
            ]);
        }

        return $member;
    }

    private function resolveLibraryId(User $actor, User $member): int
    {
        $libraryId = $actor->activeLibraryId();

        if ($actor->isSuperAdmin()) {
            $libraryId ??= $member->activeLibraryId();

            if (! $libraryId || ! $member->belongsToLibrary($libraryId)) {
                throw ValidationException::withMessages([
                    'user_id' => 'Pasirinktas narys neturi priskirtos bibliotekos.',
                ]);
            }

            return (int) $libraryId;
        }

        if (! $libraryId || ! $member->belongsToLibrary($libraryId)) {
            throw ValidationException::withMessages([
                'user_id' => 'Pasirinktas narys nepriklauso aktyviai bibliotekai.',
            ]);
        }

        return (int) $libraryId;
    }
}








