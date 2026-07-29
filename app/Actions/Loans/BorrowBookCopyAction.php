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
use App\Support\Observability\OperationDiagnostics;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BorrowBookCopyAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function handle(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        try {
            return DB::transaction(function () use ($authUser, $bookCopy, $validated): array {
                $bookCopyContext = BookCopy::query()
                    ->withoutGlobalScope('library')
                    ->whereKey($bookCopy->getKey())
                    ->firstOrFail();

                app(ReservationQueueService::class)->lockQueueContext(
                    (int) $bookCopyContext->library_id,
                    (int) $bookCopyContext->book_id
                );

                $bookCopy = BookCopy::query()
                    ->whereKey($bookCopy->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $bookCopy->loadMissing(['book:id,title', 'branch:id,name']);

                return $this->issueLocked($authUser, $bookCopy, $validated);
            });
        } catch (Throwable $exception) {
            app(OperationDiagnostics::class)->failure('loan_borrow_failed', $exception, [
                'operation' => 'loan_borrow',
                'library_id' => $bookCopy->library_id,
                'book_id' => $bookCopy->book_id,
                'book_copy_id' => $bookCopy->id,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function issueLocked(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        if (! $authUser->canManageBookCopy($bookCopy)) {
            throw ValidationException::withMessages([
                'book_copy' => ['Neturite teises isduoti kito filialo kopijos.'],
            ]);
        }

        if ($bookCopy->status !== BookCopy::STATUS_AVAILABLE) {
            throw ValidationException::withMessages([
                'book_copy' => ['Sios kopijos isduoti negalima.'],
            ]);
        }

        $queueService = app(ReservationQueueService::class);
        $notificationService = app(ReservationNotificationService::class);

        $assignedReadyReservation = Reservation::query()
            ->where('library_id', $bookCopy->library_id)
            ->where('book_id', $bookCopy->book_id)
            ->where('assigned_book_copy_id', $bookCopy->id)
            ->where('status', Reservation::STATUS_READY)
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->lockForUpdate()
            ->first();

        $priorityReservation = $queueService->getEligibleReservationForCopy($bookCopy, [], true);

        $member = User::query()
            ->where('id', $validated['user_id'])
            ->whereHas('libraryMemberships', fn ($membershipQuery) => $membershipQuery
                ->where('library_id', $bookCopy->library_id)
                ->where('is_active', true))
            ->where('role', User::ROLE_MEMBER)
            ->where('is_active', true)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['Narys nerastas sioje bibliotekoje.'],
            ]);
        }

        if ($assignedReadyReservation && (int) $assignedReadyReservation->user_id !== (int) $member->id) {
            throw ValidationException::withMessages([
                'reservation' => ['Si kopija priskirta kitam nariui paruostai rezervacijai.'],
            ]);
        }

        if ($priorityReservation && (int) $priorityReservation->user_id !== (int) $member->id) {
            throw ValidationException::withMessages([
                'reservation' => ['Si kopija pagal FIFO priklauso kitam rezervacijos nariui.'],
            ]);
        }

        if ($bookCopy->activeLoan()->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'book_copy' => ['Si kopija jau turi aktyvia paskola.'],
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
                    'reservation' => ['Rezervacija dar neparuosta atsiemimui.'],
                ]);
            }

            if ($reservation->expires_at !== null && $reservation->expires_at->lte(now())) {
                $reservation->update([
                    'status' => Reservation::STATUS_EXPIRED,
                    'pickup_branch_id' => null,
                    'assigned_book_copy_id' => null,
                ]);

                throw ValidationException::withMessages([
                    'reservation' => ['Rezervacijos atsiemimo terminas pasibaige.'],
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
                'book_copy' => ['Si kopija jau turi aktyvia paskola.'],
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

            $reservation->update([
                'status' => Reservation::STATUS_FULFILLED,
                'assigned_book_copy_id' => (int) $bookCopy->id,
                'fulfilled_at' => now(),
            ]);

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
                    'Rezervacija knygai "%s" ivykdyta nariui %s.',
                    $bookCopy->book?->title ?: 'nezinoma knyga',
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
                'Kopija %s isduota nariui %s.',
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

        return [
            'message' => 'Kopija sekmingai isduota.',
            'loan' => $loan,
        ];
    }

    private function isActiveLoanUniqueConstraintViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $message = $exception->getMessage();

        return ($errorInfo[0] ?? null) === '23000'
            && (str_contains($message, 'loans_active_book_copy_unique')
                || str_contains($message, 'loans.active_book_copy_id')
                || str_contains($message, 'active_book_copy_id'));
    }
}
