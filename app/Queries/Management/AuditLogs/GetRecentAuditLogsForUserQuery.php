<?php

namespace App\Queries\Management\AuditLogs;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRecentAuditLogsForUserQuery
{
    public function handle(User $user, int $perPage = 8, string $pageName = 'audit-page'): LengthAwarePaginator
    {
        $reservationIds = Reservation::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        $loanIds = Loan::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('issued_by', $user->id)
                    ->orWhere('received_by', $user->id);
            })
            ->pluck('id');

        return AuditLog::query()
            ->with(['actor:id,name,email', 'library:id,name,code'])
            ->where(function (Builder $query) use ($user, $reservationIds, $loanIds) {
                $query
                    ->where(function (Builder $userQuery) use ($user) {
                        $userQuery->where('auditable_type', $user->getMorphClass())
                            ->where('auditable_id', $user->id);
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
                    ->orWhere('metadata->target_member_id', $user->id);
            })
            ->latest()
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }
}








