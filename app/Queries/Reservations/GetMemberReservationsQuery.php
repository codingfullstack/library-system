<?php

namespace App\Queries\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GetMemberReservationsQuery
{
    public function __construct(private readonly ReservationQueueMetadata $queueMetadata)
    {
    }

    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $reservationDate = $filters['reservation_date'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);
        $libraryId = $user->activeLibraryId();
        $reservationDateRange = $this->dateRange($reservationDate);

        $query = $this->queueMetadata->apply(Reservation::query())
            ->where('user_id', $user->id)
            ->when($libraryId, fn ($builder) => $builder->where('library_id', $libraryId))
            ->with([
                'book:id,slug,title,subtitle,isbn',
                'library:id,name',
                'branch:id,name',
                'pickupBranch:id,name',
            ]);

        if ($status !== null && $status !== '') {
            $this->applyStatusFilter($query, (string) $status);
        }

        if ($reservationDateRange !== null) {
            $query->whereBetween('created_at', $reservationDateRange);
        }

        if ($search !== '') {
            $query->whereHas('book', function ($bookQuery) use ($search) {
                $bookQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
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
            ->when($reservationDateRange, fn ($query) => $query->whereBetween('created_at', $reservationDateRange))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('book', function ($bookQuery) use ($search) {
                    $bookQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                });
            });

        return [
            'all_count' => (clone $baseQuery)->count(),
            'active_count' => (clone $baseQuery)->active()->count(),
            'ready_count' => (clone $baseQuery)->current()->count(),
            'fulfilled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_FULFILLED)->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', Reservation::STATUS_CANCELLED)->count(),
            'expired_count' => (clone $baseQuery)->expired()->count(),
        ];
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'active' => $query->active(),
            Reservation::STATUS_WAITING => $query->pending(),
            Reservation::STATUS_READY => $query->current(),
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

}
