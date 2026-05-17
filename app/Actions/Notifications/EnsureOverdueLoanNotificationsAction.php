<?php

namespace App\Actions\Notifications;

use App\Models\Loan;
use App\Models\User;

class EnsureOverdueLoanNotificationsAction
{
    public function handle(User $user): void
    {
        if ($user->role !== 'narys' || ! $user->is_active) {
            return;
        }

        $overdueLoans = Loan::query()
            ->select(['id', 'library_id', 'book_copy_id', 'user_id', 'due_at', 'returned_at', 'status'])
            ->with(['bookCopy:id,book_id', 'bookCopy.book:id,title'])
            ->where('user_id', $user->id)
            ->whereNull('returned_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->subDay()->endOfDay())
            ->get();

        foreach ($overdueLoans as $loan) {
            app(CreateUserNotificationAction::class)->handle(
                $user,
                null,
                'loan_overdue',
                'Vėluojate grąžinti knygą',
                sprintf(
                    'Knyga "%s" vėluoja jau %d d. Grąžinimo terminas buvo %s.',
                    $loan->bookCopy?->book?->title ?: 'nežinoma knyga',
                    $loan->overdue_days,
                    $loan->due_at?->format('Y-m-d') ?: '-'
                ),
                [
                    'loan_id' => $loan->id,
                    'book_copy_id' => $loan->book_copy_id,
                    'book_title' => $loan->bookCopy?->book?->title,
                    'due_at' => $loan->due_at?->toDateString(),
                    'overdue_days' => $loan->overdue_days,
                ],
                Loan::class,
                $loan->id
            );
        }
    }
}








