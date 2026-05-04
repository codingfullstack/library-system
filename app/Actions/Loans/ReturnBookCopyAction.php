<?php

namespace App\Actions\Loans;

use App\Actions\AuditLogs\RecordAuditLogAction;
use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Actions\Notifications\CreateUserNotificationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
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
                'book_copy' => ['Aktyviai isduota knyga nerasta.'],
            ]);
        }

        $activeLoan->update([
            'status' => 'returned',
            'returned_at' => now(),
            'received_by' => $authUser->id,
        ]);

        if ($activeLoan->user) {
            app(CreateUserNotificationAction::class)->handle(
                $activeLoan->user,
                $authUser,
                'book_returned',
                'Knyga grazinta',
                sprintf(
                    'Knygos "%s" egzempliorius %s sekmingai grazintas.',
                    $bookCopy->book?->title ?: 'nezinoma knyga',
                    $bookCopy->inventory_code ?: ('#'.$bookCopy->id)
                ),
                [
                    'loan_id' => $activeLoan->id,
                    'book_id' => $bookCopy->book_id,
                    'book_copy_id' => $bookCopy->id,
                    'book_title' => $bookCopy->book?->title,
                    'inventory_code' => $bookCopy->inventory_code,
                    'returned_at' => $activeLoan->returned_at?->toDateTimeString(),
                ],
                \App\Models\Loan::class,
                $activeLoan->id
            );
        }

        app(ChangeBookCopyStatusAction::class)->handle(
            $bookCopy,
            BookCopy::STATUS_AVAILABLE,
            $authUser,
            'returned'
        );

        app(SyncReservationQueueAction::class)->handle($bookCopy->library_id, $bookCopy->book_id);

        app(RecordAuditLogAction::class)->handle(
            $authUser,
            'loan_returned',
            $activeLoan,
            sprintf(
                'Egzempliorius %s grazintas is nario %s.',
                $bookCopy->inventory_code,
                $activeLoan->user?->name ?: 'nezinomas narys'
            ),
            [
                'loan_id' => $activeLoan->id,
                'book_id' => $bookCopy->book_id,
                'book_title' => $bookCopy->book?->title,
                'book_copy_id' => $bookCopy->id,
                'inventory_code' => $bookCopy->inventory_code,
                'target_member_id' => $activeLoan->user_id,
                'target_member_name' => $activeLoan->user?->name,
                'received_by_id' => $authUser->id,
            ],
            $bookCopy->library_id
        );

        return [
            'message' => 'Kopija sėkmingai grąžinta.',
        ];
    }
}
