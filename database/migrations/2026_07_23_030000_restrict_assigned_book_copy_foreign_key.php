<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $deleteRule = DB::selectOne("
            SELECT rc.delete_rule
            FROM information_schema.referential_constraints rc
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_schema = rc.constraint_schema
             AND kcu.constraint_name = rc.constraint_name
             AND kcu.table_name = rc.table_name
            WHERE rc.constraint_schema = database()
              AND rc.table_name = 'reservations'
              AND kcu.column_name = 'assigned_book_copy_id'
        ")?->delete_rule;

        if ($deleteRule === 'RESTRICT' || $deleteRule === 'NO ACTION') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP FOREIGN KEY reservations_assigned_book_copy_id_foreign');
        DB::statement(
            'ALTER TABLE reservations ADD CONSTRAINT reservations_assigned_book_copy_id_foreign '.
            'FOREIGN KEY (assigned_book_copy_id) REFERENCES book_copies(id) ON DELETE RESTRICT'
        );
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Reverting assigned_book_copy_id to ON DELETE SET NULL is intentionally unsupported; it can erase reservation history.'
        );
    }
};
