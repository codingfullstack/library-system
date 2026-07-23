<?php

namespace App\Actions\Loans;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueDebugService;
use App\Services\ReservationQueueService;
use App\Support\Notifications\NotificationMessageBuilder;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BorrowBookCopyAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function handle(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        return DB::transaction(function () use ($authUser, $bookCopy, $validated): array {
            $bookCopy = BookCopy::query()
                ->whereKey($bookCopy->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $bookCopy->loadMissing(['book:id,title', 'branch:id,name']);

            return $this->issueLocked($authUser, $bookCopy, $validated);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function issueLocked(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        if (! $authUser->canManageBookCopy($bookCopy)) {
            throw ValidationException::withMessages([
                'book_copy' => ['Neturite teisės išduoti kito filialo kopijos.'],
            ]);
        }

        if ($bookCopy->status !== BookCopy::STATUS_AVAILABLE) {
            throw ValidationException::withMessages([
                'book_copy' => ['Šios kopijos išduoti negalima.'],
            ]);
        }

        if ($bookCopy->activeLoan()->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'book_copy' => ['Ši kopija jau turi aktyvią paskolą.'],
            ]);
        }

        $member = User::query()
            ->where('id', $validated['user_id'])
            ->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('library_id', $bookCopy->library_id)
                ->where('is_active', true))
            ->where('role', 'narys')
            ->where('is_active', true)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['Narys nerastas šioje bibliotekoje.'],
            ]);
        }

        $queueService = app(ReservationQueueService::class);
        $notificationService = app(ReservationNotificationService::class);

        $assignedReadyReservation = Reservation::query()
            ->where('assigned_book_copy_id', $bookCopy->id)
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->first();

        if ($assignedReadyReservation && (int) $assignedReadyReservation->user_id !== (int) $member->id) {
            throw ValidationException::withMessages([
                'reservation_override' => ['Å i kopija priskirta kitam nariui paruoÅ¡tai rezervacijai.'],
            ]);
        }

        $priorityReservation = $queueService->getEligibleReservationForCopy($bookCopy, [], true);

        $overrideReservation = $priorityReservation && $priorityReservation->user_id !== $member->id;

        if ($overrideReservation && ! ($validated['override_reservation'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation_override' => ['Ši knyga turi aktyvią rezervaciją kitam nariui.'],
            ]);
        }

        if ($overrideReservation && trim((string) ($validated['override_reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'override_reason' => ['Nurodykite, kodėl apeinate aktyvią rezervaciją.'],
            ]);
        }

        $dueAt = null;

        if (! ($validated['no_due_date'] ?? false)) {
            $dueAt = ! empty($validated['due_at'])
                ? $validated['due_at']
                : now()->addDays(14)->toDateString();
        }

        $reservation = $priorityReservation && (int) $priorityReservation->user_id === (int) $member->id
            ? $priorityReservation
            : null;

        if ($reservation) {
            if (! $reservation->isReady()) {
                throw ValidationException::withMessages([
                    'reservation' => ['Rezervacija dar neparuošta atsiėmimui.'],
                ]);
            }

            if ($reservation->expires_at !== null && $reservation->expires_at->lte(now())) {
                $expiredAttributes = [
                    'status' => Reservation::STATUS_EXPIRED,
                    'pickup_branch_id' => null,
                    'assigned_book_copy_id' => null,
                ];

                $reservation->update($expiredAttributes);

                throw ValidationException::withMessages([
                    'reservation' => ['Rezervacijos atsiėmimo terminas pasibaigė.'],
                ]);
            }
        }

        try {
            $loan = Loan::create([
                'library_id' => $bookCopy->library_id,
                'book_copy_id' => $bookCopy->id,
                'user_id' => $member->id,
                'issued_by' => $authUser->id,
                'borrowed_at' => now()->toDateString(),
                'due_at' => $dueAt,
                'status' => Loan::STATUS_ACTIVE,
                'notes' => $validated['notes'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isActiveLoanUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'book_copy' => ['Ši kopija jau turi aktyvią paskolą.'],
            ]);
        }

        $positionsBeforeFulfillment = $reservation
            ? $queueService->snapshotPositions($bookCopy->library_id, $bookCopy->book_id)
            : [];

        if ($reservation) {
            app(ReservationQueueDebugService::class)->logSnapshot('before_fulfillment', $bookCopy->library_id, $bookCopy->book_id, [
                'triggering_reservation_id' => $reservation->id,
                'triggering_copy_id' => $bookCopy->id,
                'old_positions' => $positionsBeforeFulfillment,
            ]);

            $fulfilledAttributes = [
                'status' => Reservation::STATUS_FULFILLED,
                'assigned_book_copy_id' => (int) $bookCopy->id,
                'fulfilled_at' => now(),
            ];

            $reservation->update($fulfilledAttributes);

            DB::afterCommit(fn () => app(CreateUserNotificationAction::class)->handle(
                $member,
                $authUser,
                NotificationType::RESERVATION_FULFILLED,
                null,
                NotificationMessageBuilder::reservationFulfilled($bookCopy),
                NotificationMetadataBuilder::bookCopy($bookCopy, [
                    'reservation_id' => $reservation->id,
                    'loan_id' => $loan->id,
                    'due_at' => $dueAt,
                ]),
                Reservation::class,
                $reservation->id
            ));

            app(RecordAuditLogAction::class)->handle(
                $authUser,
                'reservation_fulfilled',
                $reservation,
                sprintf(
                    'Rezervacija knygai "%s" įvykdyta nariui %s.',
                    $bookCopy->book?->title ?: 'nežinoma knyga',
                    $member->name
                ),
                [
                    'reservation_id' => $reservation->id,
                    'book_id' => $bookCopy->book_id,
                    'book_title' => $bookCopy->book?->title,
                    'book_copy_id' => $bookCopy->id,
                    'inventory_code' => $bookCopy->inventory_code,
                    'target_member_id' => $member->id,
                    'target_member_name' => $member->name,
                    'loan_id' => $loan->id,
                ],
                $bookCopy->library_id
            );
        }

        if ($positionsBeforeFulfillment !== []) {
            DB::afterCommit(fn () => $notificationService->notifyQueuePositionsChangedFromSnapshot(
                $bookCopy->library_id,
                $bookCopy->book_id,
                $positionsBeforeFulfillment
            ));

            app(ReservationQueueDebugService::class)->logSnapshot('after_fulfillment', $bookCopy->library_id, $bookCopy->book_id, [
                'triggering_reservation_id' => $reservation->id,
                'triggering_copy_id' => $bookCopy->id,
                'old_positions' => $positionsBeforeFulfillment,
                'new_positions' => $queueService->getPositionsForBook($bookCopy->library_id, $bookCopy->book_id),
            ]);
        }

        app(ChangeBookCopyStatusAction::class)->handle(
            $bookCopy,
            BookCopy::STATUS_LOANED,
            $authUser,
            'issued',
            $validated['notes'] ?? null
        );

        app(SyncReservationQueueAction::class)->handle($bookCopy->library_id, $bookCopy->book_id);

        app(RecordAuditLogAction::class)->handle(
            $authUser,
            'loan_issued',
            $loan,
            sprintf(
                'Kopija %s išduota nariui %s.',
                $bookCopy->inventory_code,
                $member->name
            ),
            [
                'loan_id' => $loan->id,
                'book_id' => $bookCopy->book_id,
                'book_copy_id' => $bookCopy->id,
                'inventory_code' => $bookCopy->inventory_code,
                'book_title' => $bookCopy->book?->title,
                'target_member_id' => $member->id,
                'target_member_name' => $member->name,
                'issued_by_id' => $authUser->id,
                'due_at' => $dueAt,
            ],
            $bookCopy->library_id
        );

        if ($overrideReservation) {
            app(RecordAuditLogAction::class)->handle(
                $authUser,
                'reservation_override_issued',
                $priorityReservation,
                sprintf(
                    'Apeita aktyvi rezervacija knygai "%s" ir kopija %s išduota nariui %s.',
                    $bookCopy->book?->title ?: 'nežinoma knyga',
                    $bookCopy->inventory_code,
                    $member->name
                ),
                [
                    'reservation_id' => $priorityReservation->id,
                    'book_id' => $bookCopy->book_id,
                    'book_title' => $bookCopy->book?->title,
                    'book_copy_id' => $bookCopy->id,
                    'inventory_code' => $bookCopy->inventory_code,
                    'reserved_for_user_id' => $priorityReservation->user_id,
                    'issued_to_user_id' => $member->id,
                    'issued_to_user_name' => $member->name,
                    'override_reason' => trim((string) $validated['override_reason']),
                    'loan_id' => $loan->id,
                ],
                $bookCopy->library_id
            );
        }

        return [
            'message' => 'Kopija sėkmingai išduota.',
            'loan' => $loan,
        ];
    }

    private function isActiveLoanUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'loans_active_book_copy_unique')
            || str_contains($message, 'loans.active_book_copy_id')
            || str_contains($message, 'active_book_copy_id');
    }
}
