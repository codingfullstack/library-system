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

        $this->replaceForeign('book_copies', 'book_copies_branch_library_fk', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('book_copies', 'book_copies_location_library_fk', ['location_id', 'library_id'], 'locations', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('library_memberships', 'memberships_branch_library_fk', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('loans', 'loans_book_copy_library_fk', ['book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('loans', 'loans_user_membership_fk', ['library_id', 'user_id'], 'library_memberships', ['library_id', 'user_id'], 'restrict', 'restrict');
        $this->replaceForeign('reservations', 'reservations_user_membership_fk', ['library_id', 'user_id'], 'library_memberships', ['library_id', 'user_id'], 'restrict', 'restrict');
        $this->replaceForeign('reservations', 'reservations_branch_library_fk', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('reservations', 'reservations_pickup_branch_library_fk', ['pickup_branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('reservations', 'reservations_assigned_copy_library_fk', ['assigned_book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'restrict', 'restrict');
        $this->replaceForeign('scan_logs', 'scan_logs_book_copy_library_fk', ['book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'restrict', 'restrict');
    }

    public function down(): void
    {
        throw new RuntimeException('Reverting tenant FK lifecycle rules can re-enable destructive history cascades.');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $references
     */
    private function replaceForeign(
        string $table,
        string $name,
        array $columns,
        string $foreignTable,
        array $references,
        string $onDelete,
        string $onUpdate,
    ): void {
        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");

        $columnList = collect($columns)->map(fn (string $column): string => "`{$column}`")->implode(', ');
        $referenceList = collect($references)->map(fn (string $column): string => "`{$column}`")->implode(', ');

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY ({$columnList}) REFERENCES `{$foreignTable}` ({$referenceList}) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }
};
