<?php

use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const READY_AT_COMMENT = 'added_by_2026_07_17_115842_reservation_lifecycle_fields';

    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'ready_at')) {
            Schema::table('reservations', function (Blueprint $table) {
                $column = $table->timestamp('ready_at')->nullable()->after('reserved_at');

                if (DB::getDriverName() === 'mysql') {
                    $column->comment(self::READY_AT_COMMENT);
                }
            });
        }

        if (DB::getDriverName() === 'mysql' && ! $this->statusEnumContains(Reservation::STATUS_READY)) {
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
            ->where('status', Reservation::STATUS_READY)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('fulfilled_at')
            ->whereNull('cancelled_at')
            ->update([
                'status' => Reservation::STATUS_EXPIRED,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('reservations', 'ready_at') && $this->readyAtWasAddedByThisMigration()) {
            Schema::table('reservations', function (Blueprint $table) {
                $table->dropColumn('ready_at');
            });
        }
    }

    private function statusEnumContains(string $value): bool
    {
        $column = DB::selectOne("SHOW COLUMNS FROM reservations WHERE Field = 'status'");
        $type = (string) ($column->Type ?? '');

        return str_contains($type, "'{$value}'");
    }

    private function readyAtWasAddedByThisMigration(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return ! $this->migrationHasRun('2026_07_17_000000_add_ready_status_to_reservations_table');
        }

        $column = DB::selectOne("SHOW FULL COLUMNS FROM reservations WHERE Field = 'ready_at'");

        return (string) ($column->Comment ?? '') === self::READY_AT_COMMENT;
    }

    private function migrationHasRun(string $migration): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')->where('migration', $migration)->exists();
    }
};
