<?php

namespace App\Queries\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetMemberReservationsQuery
{
    public function handle(User $user, array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 15);

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

        $query = Reservation::query()
            ->select('reservations.*')
            ->selectSub($queuePositionSubquery, 'queue_position')
            ->where('user_id', $user->id)
            ->with([
                'book:id,title,subtitle,isbn',
                'library:id,name',
            ]);

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->whereHas('book', function ($bookQuery) use ($search) {
                $bookQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('isbn', 'like', "%{$search}%");
            });
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
