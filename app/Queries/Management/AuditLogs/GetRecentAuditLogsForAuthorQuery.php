<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetRecentAuditLogsForAuthorQuery
{
    /**
     * @return Collection<int, AuditLog>
     */
    public function handle(Author $author, int $limit = 18): Collection
    {
        $bookIds = Book::query()
            ->whereHas('authors', fn (Builder $query) => $query->where('authors.id', $author->id))
            ->pluck('id');

        $bookCopyIds = BookCopy::query()->whereIn('book_id', $bookIds)->pluck('id');
        $reservationIds = Reservation::query()->whereIn('book_id', $bookIds)->pluck('id');
        $loanIds = Loan::query()
            ->whereHas('bookCopy', fn (Builder $query) => $query->whereIn('book_id', $bookIds))
            ->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($author, $bookIds, $bookCopyIds, $reservationIds, $loanIds) {
                $query
                    ->where(function (Builder $selfQuery) use ($author) {
                        $selfQuery->where('auditable_type', $author->getMorphClass())
                            ->where('auditable_id', $author->id);
                    })
                    ->orWhere(function (Builder $bookQuery) use ($bookIds) {
                        if ($bookIds->isEmpty()) {
                            $bookQuery->whereRaw('1 = 0');

                            return;
                        }

                        $bookQuery->where('auditable_type', (new Book())->getMorphClass())
                            ->whereIn('auditable_id', $bookIds);
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
                    ->orWhereIn('metadata->book_id', $bookIds);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }
}
