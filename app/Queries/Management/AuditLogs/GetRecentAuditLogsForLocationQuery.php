<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\BookCopy;
use App\Models\Loan;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetRecentAuditLogsForLocationQuery
{
    /**
     * @return Collection<int, AuditLog>
     */
    public function handle(Location $location, int $limit = 18): Collection
    {
        $bookCopyIds = BookCopy::query()->where('location_id', $location->id)->pluck('id');
        $loanIds = Loan::query()->whereIn('book_copy_id', $bookCopyIds)->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($location, $bookCopyIds, $loanIds) {
                $query
                    ->where(function (Builder $selfQuery) use ($location) {
                        $selfQuery->where('auditable_type', $location->getMorphClass())
                            ->where('auditable_id', $location->id);
                    })
                    ->orWhere(function (Builder $copyQuery) use ($bookCopyIds) {
                        if ($bookCopyIds->isEmpty()) {
                            $copyQuery->whereRaw('1 = 0');

                            return;
                        }

                        $copyQuery->where('auditable_type', (new BookCopy())->getMorphClass())
                            ->whereIn('auditable_id', $bookCopyIds);
                    })
                    ->orWhere(function (Builder $loanQuery) use ($loanIds) {
                        if ($loanIds->isEmpty()) {
                            $loanQuery->whereRaw('1 = 0');

                            return;
                        }

                        $loanQuery->where('auditable_type', (new Loan())->getMorphClass())
                            ->whereIn('auditable_id', $loanIds);
                    })
                    ->orWhere('metadata->location_id', $location->id);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }
}








