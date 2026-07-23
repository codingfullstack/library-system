<?php

use App\Models\Reservation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'reservations_ready_completeness_check';

    public function up(): void
    {
        $invalidIds = DB::table('reservations')
            ->where('status', Reservation::STATUS_READY)
            ->where(function ($query) {
                $query
                    ->whereNull('assigned_book_copy_id')
                    ->orWhereNull('pickup_branch_id')
                    ->orWhereNull('ready_at')
                    ->orWhereNull('expires_at');
            })
            ->limit(20)
            ->pluck('id');

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add READY completeness check: incomplete READY reservation IDs '.
                $invalidIds->implode(', ').'.'
            );
        }

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("
            ALTER TABLE reservations
            ADD CONSTRAINT ".self::CONSTRAINT."
            CHECK (
                status <> '".str_replace("'", "''", Reservation::STATUS_READY)."'
                OR (
                    assigned_book_copy_id IS NOT NULL
                    AND pickup_branch_id IS NOT NULL
                    AND ready_at IS NOT NULL
                    AND expires_at IS NOT NULL
                )
            )
        ");
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT '.self::CONSTRAINT);
    }
};
