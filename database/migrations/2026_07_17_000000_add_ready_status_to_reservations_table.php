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
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('ready_at')->nullable()->after('reserved_at');
            $table->index(['status', 'expires_at'], 'reservations_status_expires_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf(
                "ALTER TABLE reservations MODIFY status ENUM('%s', '%s', '%s', '%s', '%s') NOT NULL DEFAULT '%s'",
                Reservation::STATUS_RESERVED,
                Reservation::STATUS_READY,
                Reservation::STATUS_FULFILLED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_EXPIRED,
                Reservation::STATUS_RESERVED,
            ));
        }

        DB::table('reservations')
            ->where('status', Reservation::STATUS_RESERVED)
            ->whereNotNull('expires_at')
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->update([
                'status' => Reservation::STATUS_READY,
                'ready_at' => DB::raw('reserved_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('reservations')
            ->where('status', Reservation::STATUS_READY)
            ->update([
                'status' => Reservation::STATUS_RESERVED,
                'ready_at' => null,
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(sprintf(
                "ALTER TABLE reservations MODIFY status ENUM('%s', '%s', '%s', '%s') NOT NULL DEFAULT '%s'",
                Reservation::STATUS_RESERVED,
                Reservation::STATUS_FULFILLED,
                Reservation::STATUS_CANCELLED,
                Reservation::STATUS_EXPIRED,
                Reservation::STATUS_RESERVED,
            ));
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_status_expires_index');
            $table->dropColumn('ready_at');
        });
    }
};
