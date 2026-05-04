<?php

namespace App\Actions\Notifications;

use App\Models\Loan;
use App\Models\User;

class EnsureOverdueLoanNotificationsAction
{
    public function handle(User $user): void
    {
        if ($user->role !== 'member' || ! $user->is_active) {
            return;
        }

        $overdueLoans = Loan::query()
            ->with(['bookCopy.book:id,title'])
            ->where('user_id', $user->id)
            ->whereNull('returned_at')
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<=', now()->subDay()->toDateString())
            ->get();

        foreach ($overdueLoans as $loan) {
            app(CreateUserNotificationAction::class)->handle(
                $user,
                null,
                'loan_overdue',
                'Veluojate grazinti knyga',
                sprintf(
                    'Knyga "%s" veluoja jau %d d. Grazinimo terminas buvo %s.',
                    $loan->bookCopy?->book?->title ?: 'nezinoma knyga',
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
