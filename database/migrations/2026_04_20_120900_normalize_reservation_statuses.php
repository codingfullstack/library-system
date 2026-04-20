<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reservations MODIFY status ENUM('active', 'reserved', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'reserved'");
        }

        DB::table('reservations')
            ->where('status', 'active')
            ->update(['status' => 'reserved']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reservations MODIFY status ENUM('reserved', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'reserved'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reservations')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reservations MODIFY status ENUM('active', 'reserved', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'active'");
        }

        DB::table('reservations')
            ->where('status', 'reserved')
            ->update(['status' => 'active']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reservations MODIFY status ENUM('active', 'fulfilled', 'cancelled', 'expired') NOT NULL DEFAULT 'active'");
        }
    }
};
