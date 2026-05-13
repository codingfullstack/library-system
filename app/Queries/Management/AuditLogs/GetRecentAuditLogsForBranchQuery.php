<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetRecentAuditLogsForBranchQuery
{
    /**
     * @return Collection<int, AuditLog>
     */
    public function handle(Branch $branch, int $limit = 18): Collection
    {
        $locationIds = Location::query()->where('branch_id', $branch->id)->pluck('id');
        $bookCopyIds = BookCopy::query()->where('branch_id', $branch->id)->pluck('id');
        $loanIds = Loan::query()->whereIn('book_copy_id', $bookCopyIds)->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($branch, $locationIds, $bookCopyIds, $loanIds) {
                $query
                    ->where(function (Builder $selfQuery) use ($branch) {
                        $selfQuery->where('auditable_type', $branch->getMorphClass())
                            ->where('auditable_id', $branch->id);
                    })
                    ->orWhere(function (Builder $locationQuery) use ($locationIds) {
                        if ($locationIds->isEmpty()) {
                            $locationQuery->whereRaw('1 = 0');

                            return;
                        }

                        $locationQuery->where('auditable_type', (new Location())->getMorphClass())
                            ->whereIn('auditable_id', $locationIds);
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
                    ->orWhere('metadata->branch_id', $branch->id);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }
}








