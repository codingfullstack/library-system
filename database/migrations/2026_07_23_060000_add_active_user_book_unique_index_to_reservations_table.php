<?php

use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateActiveReservations();

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_reservation_marker')
                ->nullable()
                ->virtualAs($this->activeReservationMarkerExpression());

            $table->unique(
                ['library_id', 'book_id', 'user_id', 'active_reservation_marker'],
                'reservations_active_user_book_unique'
            );
        });
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_active_user_book_unique');
            $table->dropColumn('active_reservation_marker');
        });
    }

    private function assertNoDuplicateActiveReservations(): void
    {
        $duplicates = DB::table('reservations')
            ->select('library_id', 'book_id', 'user_id', DB::raw('count(*) as active_count'))
            ->whereIn('status', [Reservation::STATUS_WAITING, Reservation::STATUS_READY])
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->groupBy('library_id', 'book_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $conflicts = $duplicates
            ->map(fn ($row): string => sprintf(
                'library_id=%s book_id=%s user_id=%s',
                $row->library_id,
                $row->book_id,
                $row->user_id
            ))
            ->implode('; ');

        throw new RuntimeException(
            'Cannot add active reservation invariant: duplicate active reservations exist for '.$conflicts.'.'
        );
    }

    private function activeReservationMarkerExpression(): string
    {
        return "case when status in ('".
            implode("', '", array_map(
                fn (string $status): string => str_replace("'", "''", $status),
                [Reservation::STATUS_WAITING, Reservation::STATUS_READY]
            )).
            "') and fulfilled_at is null and cancelled_at is null then 1 else null end";
    }
};
