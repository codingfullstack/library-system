<?php

namespace App\Queries\Notifications;

use App\Models\User;
use App\Support\Notifications\NotificationUiConfig;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class GetUserNotificationsQuery
{
    public function handle(User $user, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        $query = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->when(($filters['category'] ?? 'all') !== 'all', function (Builder $builder) use ($filters) {
                $types = NotificationUiConfig::typesForCategory((string) $filters['category']);

                $types === []
                    ? $builder->whereRaw('1 = 0')
                    : $builder->whereIn('type', $types);
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
