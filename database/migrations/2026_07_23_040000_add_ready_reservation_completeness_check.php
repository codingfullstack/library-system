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

        $this->restrictPickupBranchForeignKey();

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

    private function restrictPickupBranchForeignKey(): void
    {
        $foreignKey = DB::selectOne("
            SELECT rc.DELETE_RULE AS delete_rule
            FROM information_schema.referential_constraints rc
            WHERE rc.constraint_schema = database()
              AND rc.table_name = 'reservations'
              AND rc.constraint_name = 'reservations_pickup_branch_id_foreign'
        ");

        $deleteRule = $foreignKey->delete_rule ?? $foreignKey->DELETE_RULE ?? null;

        if ($deleteRule === 'RESTRICT' || $deleteRule === 'NO ACTION') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP FOREIGN KEY reservations_pickup_branch_id_foreign');
        DB::statement(
            'ALTER TABLE reservations ADD CONSTRAINT reservations_pickup_branch_id_foreign '.
            'FOREIGN KEY (pickup_branch_id) REFERENCES branches(id) ON DELETE RESTRICT'
        );
    }
};
