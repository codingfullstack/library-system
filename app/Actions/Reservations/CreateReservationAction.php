<?php

namespace App\Actions\Reservations;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueService;
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

        [$scope, $branchId] = $this->resolveScope($actor, $libraryId, $data);

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
                'book_id' => 'Šis narys jau turi aktyviai išduota šia knyga.',
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
                'book_id' => 'Šis narys jau turi laukiancia šios knygos rezervacija.',
            ]);
        }

        $queueService = app(ReservationQueueService::class);
        $hasAvailableCopy = $queueService->hasAvailableCopies($libraryId, $book->id, $scope, $branchId);

        if ($hasAvailableCopy) {
            throw ValidationException::withMessages([
                'book_id' => $scope === Reservation::SCOPE_BRANCH
                    ? 'Knyga šiuo metu prieinama pasirinktame filiale, rezervacija nereikalinga.'
                    : 'Knyga šiuo metu prieinama pasirinktoje bibliotekoje, rezervacija nereikalinga.',
            ]);
        }

        $hasQueueAhead = $queueService
            ->pendingReservationsQuery($libraryId, $book->id, $scope, $branchId)
            ->exists();

        $reservation = Reservation::create([
            'library_id' => $libraryId,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'scope' => $scope,
            'branch_id' => $branchId,
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
        app(ReservationNotificationService::class)->notifyCreated($reservation->fresh());

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
                'scope' => $scope,
                'branch_id' => $branchId,
                'expires_at' => $reservation->expires_at?->toDateTimeString(),
            ],
            $libraryId
        );

        return $reservation->fresh();
    }

    private function resolveMember(User $actor, array $data): User
    {
        if ($actor->effectiveRole() === User::ROLE_MEMBER) {
            if (! $actor->is_active) {
                throw ValidationException::withMessages([
                    'user' => 'Jusu paskyra nera aktyvi.',
                ]);
            }

            return $actor;
        }

        if (! $actor->hasAnyEffectiveRole([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_STAFF])) {
            throw ValidationException::withMessages([
                'user' => 'Neturite teises kurti rezervacijos.',
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
            ->where('role', User::ROLE_MEMBER)
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

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveScope(User $actor, int $libraryId, array $data): array
    {
        $scope = (string) ($data['scope'] ?? Reservation::SCOPE_LIBRARY);

        if (! in_array($scope, [Reservation::SCOPE_BRANCH, Reservation::SCOPE_LIBRARY], true)) {
            throw ValidationException::withMessages([
                'scope' => 'Pasirinkite galiojančią rezervacijos apimtį.',
            ]);
        }

        if ($scope === Reservation::SCOPE_LIBRARY) {
            return [Reservation::SCOPE_LIBRARY, null];
        }

        $branchId = isset($data['branch_id']) && $data['branch_id'] !== ''
            ? (int) $data['branch_id']
            : null;

        if ($actor->role === User::ROLE_STAFF) {
            $staffBranchId = $actor->assignedBranchId($libraryId);

            if (! $staffBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Darbuotojas turi būti priskirtas filialui.',
                ]);
            }

            $branchId ??= $staffBranchId;

            if ((int) $branchId !== (int) $staffBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Darbuotojas gali kurti filialo rezervacijas tik savo filiale.',
                ]);
            }
        }

        if (! $branchId) {
            throw ValidationException::withMessages([
                'branch_id' => 'Pasirinkite filialą filialo apimties rezervacijai.',
            ]);
        }

        $branchBelongsToLibrary = Branch::query()
            ->whereKey($branchId)
            ->where('library_id', $libraryId)
            ->exists();

        if (! $branchBelongsToLibrary) {
            throw ValidationException::withMessages([
                'branch_id' => 'Pasirinktas filialas nepriklauso aktyviai bibliotekai.',
            ]);
        }

        return [Reservation::SCOPE_BRANCH, $branchId];
    }
}
