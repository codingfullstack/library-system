<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetRecentAuditLogsForCategoryQuery
{
    /**
     * @return Collection<int, AuditLog>
     */
    public function handle(Category $category, int $limit = 18): Collection
    {
        $bookIds = Book::query()
            ->where('category_id', $category->id)
            ->orWhereHas('categories', fn (Builder $query) => $query->where('categories.id', $category->id))
            ->pluck('id');

        return $this->logsForBooksAndSelf($category, $bookIds, $limit);
    }

    /**
     * @param Collection<int, int> $bookIds
     * @return Collection<int, AuditLog>
     */
    private function logsForBooksAndSelf(Category $category, Collection $bookIds, int $limit): Collection
    {
        $bookCopyIds = BookCopy::query()->whereIn('book_id', $bookIds)->pluck('id');
        $reservationIds = Reservation::query()->whereIn('book_id', $bookIds)->pluck('id');
        $loanIds = Loan::query()
            ->whereHas('bookCopy', fn (Builder $query) => $query->whereIn('book_id', $bookIds))
            ->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($category, $bookIds, $bookCopyIds, $reservationIds, $loanIds) {
                $query
                    ->where(function (Builder $selfQuery) use ($category) {
                        $selfQuery->where('auditable_type', $category->getMorphClass())
                            ->where('auditable_id', $category->id);
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








