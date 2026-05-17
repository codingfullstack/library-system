<?php

namespace App\Queries\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetUserNotificationsQuery
{
    public function handle(User $user, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        $query = UserNotification::query()
            ->with('sender:id,name,email')
            ->where('user_id', $user->id)
            ->when(($filters['category'] ?? 'all') !== 'all', function (Builder $builder) use ($filters) {
                $types = match ($filters['category']) {
                    'system' => ['system', 'new_user', 'qr_scan'],
                    'reminder' => ['loan_overdue', 'reservation_ready'],
                    'reservation' => ['reservation_ready', 'reservation_cancelled', 'reservation_fulfilled'],
                    'warning' => ['loan_overdue', 'reservation_cancelled'],
                    'info' => ['book_returned'],
                    'report' => ['report_ready', 'issuance_summary'],
                    default => [],
                };

                if ($types !== []) {
                    $builder->whereIn('type', $types);
                }
            })
            ->when(($filters['status'] ?? 'all') !== 'all', function (Builder $builder) use ($filters) {
                if ($filters['status'] === 'unread') {
                    $builder->whereNull('read_at');
                }

                if ($filters['status'] === 'read') {
                    $builder->whereNotNull('read_at');
                }
            })
            ->when(! empty($filters['date']), function (Builder $builder) use ($filters) {
                $date = Carbon::parse($filters['date']);
                $builder->whereBetween('created_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
            });

        match ($filters['sort'] ?? 'latest') {
            'oldest' => $query->oldest(),
            'unread_first' => $query->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->latest(),
            default => $query->latest(),
        };

        return $query->paginate($perPage)->withQueryString();
    }
}








