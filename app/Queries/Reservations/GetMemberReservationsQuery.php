<?php

namespace App\Queries\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GetMemberReservationsQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $reservationDate = $filters['reservation_date'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);
        $libraryId = $user->activeLibraryId();
        $reservationDateRange = $this->dateRange($reservationDate);

        $queuePositionSubquery = $this->queuePositionSubquery();

        $query = Reservation::query()
            ->select('reservations.*')
            ->selectSub($queuePositionSubquery, 'queue_position')
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($builder) => $builder->where('library_id', $libraryId))
            ->with([
                'book:id,slug,title,subtitle,isbn',
                'library:id,name',
                'branch:id,name',
            ]);

        if ($status !== null && $status !== '') {
            $this->applyStatusFilter($query, (string) $status);
        }

        if ($reservationDateRange !== null) {
            $query->whereBetween('reserved_at', $reservationDateRange);
        }

        if ($search !== '') {
            $query->whereHas('book', function ($bookQuery) use ($search) {
                $bookQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('reserved_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(User $user, array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $reservationDate = $filters['reservation_date'] ?? null;
        $libraryId = $user->activeLibraryId();
        $reservationDateRange = $this->dateRange($reservationDate);

        $baseQuery = Reservation::query()
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($query) => $query->where('library_id', $libraryId))
            ->when($status, fn ($query) => $this->applyStatusFilter($query, (string) $status))
            ->when($reservationDateRange, fn ($query) => $query->whereBetween('reserved_at', $reservationDateRange))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('book', function ($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                });
            });

        return [
            'all_count' => (clone $baseQuery)->count(),
            'active_count' => (clone $baseQuery)->pending()->count(),
            'fulfilled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_FULFILLED)->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_CANCELLED)->count(),
            'expired_count' => (clone $baseQuery)->expired()->count(),
        ];
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            Reservation::STATUS_RESERVED => $query->pending(),
            Reservation::STATUS_EXPIRED => $query->expired(),
            default => $query->where('status', $status),
        };
    }

    private function dateRange(mixed $date): ?array
    {
        if (empty($date)) {
            return null;
        }

        $parsedDate = CarbonImmutable::parse($date);

        return [$parsedDate->startOfDay(), $parsedDate->endOfDay()];
    }

    private function queuePositionSubquery()
    {
        return DB::table('reservations as queue_reservations')
            ->selectRaw('COUNT(*)')
            ->whereColumn('queue_reservations.book_id', 'reservations.book_id')
            ->whereColumn('queue_reservations.library_id', 'reservations.library_id')
            ->where(function ($scopeQuery) {
                $scopeQuery->where(function ($libraryScopeQuery) {
                    $libraryScopeQuery
                        ->whereColumn('queue_reservations.scope', 'reservations.scope')
                        ->where('queue_reservations.scope', Reservation::SCOPE_LIBRARY)
                        ->whereNull('queue_reservations.branch_id')
                        ->whereNull('reservations.branch_id');
                })->orWhere(function ($branchScopeQuery) {
                    $branchScopeQuery
                        ->whereColumn('queue_reservations.scope', 'reservations.scope')
                        ->where('queue_reservations.scope', Reservation::SCOPE_BRANCH)
                        ->whereColumn('queue_reservations.branch_id', 'reservations.branch_id');
                });
            })
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
    }
}
