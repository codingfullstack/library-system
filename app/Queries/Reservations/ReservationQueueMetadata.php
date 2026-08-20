<?php

namespace App\Queries\Reservations;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReservationQueueMetadata
{
    public function apply(Builder $query): Builder
    {
        return $query
            ->select('reservations.*')
            ->selectSub($this->queuePositionSubquery(), 'queue_position')
            ->selectSub($this->queueSizeSubquery(), 'queue_size');
    }

    private function queuePositionSubquery()
    {
        return DB::table('reservations as queue_reservations')
            ->selectRaw('COUNT(*)')
            ->whereColumn('queue_reservations.book_id', 'reservations.book_id')
            ->whereColumn('queue_reservations.library_id', 'reservations.library_id')
            ->where('queue_reservations.status', Reservation::STATUS_WAITING)
            ->whereNull('queue_reservations.fulfilled_at')
            ->whereNull('queue_reservations.cancelled_at')
            ->where(function ($queueQuery) {
                $queueQuery->whereColumn('queue_reservations.created_at', '<', 'reservations.created_at')
                    ->orWhere(function ($sameTimeQuery) {
                        $sameTimeQuery->whereColumn('queue_reservations.created_at', '=', 'reservations.created_at')
                            ->whereColumn('queue_reservations.id', '<=', 'reservations.id');
                    });
            });
    }

    private function queueSizeSubquery()
    {
        return DB::table('reservations as queue_reservations')
            ->selectRaw('COUNT(*)')
            ->whereColumn('queue_reservations.book_id', 'reservations.book_id')
            ->whereColumn('queue_reservations.library_id', 'reservations.library_id')
            ->where('queue_reservations.status', Reservation::STATUS_WAITING)
            ->whereNull('queue_reservations.fulfilled_at')
            ->whereNull('queue_reservations.cancelled_at');
    }
}
