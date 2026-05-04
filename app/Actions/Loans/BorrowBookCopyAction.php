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
use Illuminate\Validation\ValidationException;

class BorrowBookCopyAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function handle(User $authUser, BookCopy $bookCopy, array $validated): array
    {
        if ($bookCopy->status !== BookCopy::STATUS_AVAILABLE) {
            throw ValidationException::withMessages([
                'book_copy' => ['Sios kopijos isduoti negalima.'],
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
                'user_id' => ['Narys nerastas sioje bibliotekoje.'],
            ]);
        }

        $priorityReservation = Reservation::query()
            ->where('library_id', $bookCopy->library_id)
            ->where('book_id', $bookCopy->book_id)
            ->pending()
            ->orderBy('reserved_at')
            ->first();

        $overrideReservation = $priorityReservation && $priorityReservation->user_id !== $member->id;

        if ($overrideReservation && ! ($validated['override_reservation'] ?? false)) {
            throw ValidationException::withMessages([
                'reservation_override' => ['Si knyga turi aktyvia rezervacija kitam nariui.'],
            ]);
        }

        if ($overrideReservation && trim((string) ($validated['override_reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'override_reason' => ['Nurodykite, kodel apeinate aktyvia rezervacija.'],
            ]);
        }

        $dueAt = null;

        if (! ($validated['no_due_date'] ?? false)) {
            $dueAt = ! empty($validated['due_at'])
                ? $validated['due_at']
                : now()->addDays(14)->toDateString();
        }

        $loan = Loan::create([
            'library_id' => $bookCopy->library_id,
            'book_copy_id' => $bookCopy->id,
            'user_id' => $member->id,
            'issued_by' => $authUser->id,
            'borrowed_at' => now()->toDateString(),
            'due_at' => $dueAt,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        $reservation = Reservation::query()
            ->where('library_id', $bookCopy->library_id)
            ->where('book_id', $bookCopy->book_id)
            ->where('user_id', $member->id)
            ->pending()
            ->first();

        if ($reservation) {
            $reservation->update([
                'status' => Reservation::STATUS_FULFILLED,
                'fulfilled_at' => now(),
            ]);

            app(CreateUserNotificationAction::class)->handle(
                $member,
                $authUser,
                'reservation_fulfilled',
                'Rezervacija ivykdyta',
                sprintf(
                    'Pagal jusu rezervacija isduota knyga "%s".',
                    $bookCopy->book?->title ?: 'nezinoma knyga'
                ),
                [
                    'reservation_id' => $reservation->id,
                    'loan_id' => $loan->id,
                    'book_id' => $bookCopy->book_id,
                    'book_copy_id' => $bookCopy->id,
                    'book_title' => $bookCopy->book?->title,
                    'inventory_code' => $bookCopy->inventory_code,
                    'due_at' => $dueAt,
                ],
                Reservation::class,
                $reservation->id
            );

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
                'Egzempliorius %s isduotas nariui %s.',
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
                    'Apeita aktyvi rezervacija knygai "%s" ir egzempliorius %s isduotas nariui %s.',
                    $bookCopy->book?->title ?: 'nezinoma knyga',
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
            'message' => 'Kopija sekmingai isduota.',
            'loan' => $loan,
        ];
    }
}
