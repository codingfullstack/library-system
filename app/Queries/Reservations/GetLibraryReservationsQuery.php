<?php

namespace App\Queries\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetLibraryReservationsQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $queuePositionSubquery = DB::table('reservations as queue_reservations')
            ->selectRaw('COUNT(*)')
            ->whereColumn('queue_reservations.book_id', 'reservations.book_id')
            ->whereColumn('queue_reservations.library_id', 'reservations.library_id')
            ->where('queue_reservations.status', Reservation::STATUS_RESERVED)
            ->whereNull('queue_reservations.fulfilled_at')
            ->whereNull('queue_reservations.cancelled_at')
            ->where(function ($expiresQuery) {
                $expiresQuery->whereNull('queue_reservations.expires_at')
                    ->orWhere('queue_reservations.expires_at', '>', now());
            })
            ->where(function ($queueQuery) {
                $queueQuery->whereColumn('queue_reservations.reserved_at', '<', 'reservations.reserved_at')
                    ->orWhere(function ($sameTimeQuery) {
                        $sameTimeQuery->whereColumn('queue_reservations.reserved_at', '=', 'reservations.reserved_at')
                            ->whereColumn('queue_reservations.id', '<=', 'reservations.id');
                    });
            });

        $query = $this->baseQuery($user, $filters)
            ->select('reservations.*')
            ->selectSub($queuePositionSubquery, 'queue_position')
            ->with([
                'book:id,title,subtitle,isbn',
                'user:id,name,email,membership_number',
                'library:id,name',
            ]);

        $queue = $filters['queue'] ?? null;

        if ($queue === 'first') {
            $query->pending()->where($queuePositionSubquery, '=', 1);
        }

        if ($queue === 'waiting') {
            $query->pending()->where($queuePositionSubquery, '>', 1);
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(User $user, array $filters = []): array
    {
        $baseQuery = $this->baseQuery($user, $filters);

        return [
            'all_count' => (clone $baseQuery)->count(),
            'active_count' => (clone $baseQuery)->where('status', Reservation::STATUS_RESERVED)->count(),
            'fulfilled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_FULFILLED)->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_CANCELLED)->count(),
            'expired_count' => (clone $baseQuery)->where('status', Reservation::STATUS_EXPIRED)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function baseQuery(User $user, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $libraryId = $user->isSuperAdmin() ? ($filters['library_id'] ?? null) : $user->activeLibraryId();
        $reservationDate = $filters['reservation_date'] ?? null;

        return Reservation::query()
            ->when(! empty($libraryId), fn ($builder) => $builder->where('library_id', $libraryId))
            ->when(! empty($status), fn ($builder) => $builder->where('status', $status))
            ->when(! empty($reservationDate), fn ($builder) => $builder->whereDate('reserved_at', $reservationDate))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('book', function ($bookQuery) use ($search) {
                        $bookQuery->where('title', 'like', "%{$search}%")
                            ->orWhere('isbn', 'like', "%{$search}%");
                    })->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('membership_number', 'like', "%{$search}%");
                    })->orWhereHas('library', function ($libraryQuery) use ($search) {
                        $libraryQuery->where('name', 'like', "%{$search}%");
                    });
                });
            });
    }
}








