<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRecentAuditLogsForBookQuery
{
    public function handle(Book $book, int $perPage = 8, string $pageName = 'audit-page'): LengthAwarePaginator
    {
        $bookCopyIds = BookCopy::query()
            ->where('book_id', $book->id)
            ->pluck('id');

        $reservationIds = Reservation::query()
            ->where('book_id', $book->id)
            ->pluck('id');

        $loanIds = Loan::query()
            ->whereHas('bookCopy', fn (Builder $query) => $query->where('book_id', $book->id))
            ->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($book, $bookCopyIds, $reservationIds, $loanIds) {
                $query
                    ->where(function (Builder $bookQuery) use ($book) {
                        $bookQuery->where('auditable_type', $book->getMorphClass())
                            ->where('auditable_id', $book->id);
                    })
                    ->orWhere(function (Builder $copyQuery) use ($bookCopyIds) {
                        if ($bookCopyIds->isEmpty()) {
                            $copyQuery->whereRaw('1 = 0');

                            return;
                        }

                        $copyQuery->where('auditable_type', (new BookCopy())->getMorphClass())
                            ->whereIn('auditable_id', $bookCopyIds);
                    })
                    ->orWhere(function (Builder $reservationQuery) use ($reservationIds) {
                        if ($reservationIds->isEmpty()) {
                            $reservationQuery->whereRaw('1 = 0');

                            return;
                        }

                        $reservationQuery->where('auditable_type', (new Reservation())->getMorphClass())
                            ->whereIn('auditable_id', $reservationIds);
                    })
                    ->orWhere(function (Builder $loanQuery) use ($loanIds) {
                        if ($loanIds->isEmpty()) {
                            $loanQuery->whereRaw('1 = 0');

                            return;
                        }

                        $loanQuery->where('auditable_type', (new Loan())->getMorphClass())
                            ->whereIn('auditable_id', $loanIds);
                    })
                    ->orWhere('metadata->book_id', $book->id);
            })
            ->latest()
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }
}








