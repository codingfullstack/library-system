<?php

namespace App\Actions\Notifications;

use App\Models\Loan;
use App\Models\User;
use App\Support\Notifications\NotificationMessageBuilder;
use App\Support\Notifications\NotificationMetadataBuilder;
use App\Support\Notifications\NotificationType;

class EnsureOverdueLoanNotificationsAction
{
    public function handle(User $user): void
    {
        if ($user->role !== 'narys' || ! $user->is_active) {
            return;
        }

        $overdueLoans = Loan::query()
            ->select(['id', 'library_id', 'book_copy_id', 'user_id', 'due_at', 'returned_at', 'status'])
            ->with(['bookCopy:id,book_id', 'bookCopy.book:id,slug,title'])
            ->where('user_id', $user->id)
            ->whereNull('returned_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->subDay()->endOfDay())
            ->get();

        foreach ($overdueLoans as $loan) {
            app(CreateUserNotificationAction::class)->handle(
                $user,
                null,
                NotificationType::LOAN_OVERDUE,
                null,
                NotificationMessageBuilder::loanOverdue($loan),
                NotificationMetadataBuilder::loan($loan, [
                    'overdue_days' => $loan->overdue_days,
                ]),
                Loan::class,
                $loan->id
            );
        }
    }
}
