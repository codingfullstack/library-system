<?php

use App\Support\Tenancy\TenantIntegrityAuditor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertTenantIntegrityIsClean();

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->addCompositeIndexes();
        $this->addCompositeChildIndexes();
        $this->replaceForeignKeys();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->dropCompositeForeignKeys();
        $this->restoreSimpleForeignKeys();
        $this->dropCompositeChildIndexes();
        $this->dropCompositeIndexes();
    }

    private function assertTenantIntegrityIsClean(): void
    {
        $violations = app(TenantIntegrityAuditor::class)->violations();

        if ($violations->isEmpty()) {
            return;
        }

        $summary = $violations
            ->map(fn ($violation) => sprintf(
                '%s#%d %s library_id=%s related=%s#%s',
                $violation->table,
                $violation->id,
                $violation->type,
                $violation->libraryId ?? 'null',
                $violation->relatedTable ?? 'null',
                $violation->relatedId ?? 'null',
            ))
            ->implode('; ');

        throw new RuntimeException('Cannot add tenant ownership constraints while violations exist: '.$summary);
    }

    private function addCompositeIndexes(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unique(['id', 'library_id'], 'branches_id_library_unique');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->unique(['id', 'library_id'], 'locations_id_library_unique');
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->unique(['id', 'library_id'], 'book_copies_id_library_unique');
        });
    }

    private function dropCompositeIndexes(): void
    {
        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropUnique('book_copies_id_library_unique');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('locations_id_library_unique');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique('branches_id_library_unique');
        });
    }

    private function addCompositeChildIndexes(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->index(['branch_id', 'library_id'], 'locations_branch_library_index');
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->index(['branch_id', 'library_id'], 'book_copies_branch_library_index');
            $table->index(['location_id', 'library_id'], 'book_copies_location_library_index');
        });

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->index(['branch_id', 'library_id'], 'memberships_branch_library_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index(['book_copy_id', 'library_id'], 'loans_book_copy_library_index');
            $table->index(['library_id', 'user_id'], 'loans_user_membership_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['library_id', 'user_id'], 'reservations_user_membership_index');
            $table->index(['branch_id', 'library_id'], 'reservations_branch_library_index');
            $table->index(['pickup_branch_id', 'library_id'], 'reservations_pickup_branch_library_index');
            $table->index(['assigned_book_copy_id', 'library_id'], 'reservations_assigned_copy_library_index');
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->index(['book_copy_id', 'library_id'], 'scan_logs_book_copy_library_index');
        });
    }

    private function dropCompositeChildIndexes(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex('scan_logs_book_copy_library_index');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_assigned_copy_library_index');
            $table->dropIndex('reservations_pickup_branch_library_index');
            $table->dropIndex('reservations_branch_library_index');
            $table->dropIndex('reservations_user_membership_index');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex('loans_user_membership_index');
            $table->dropIndex('loans_book_copy_library_index');
        });

        Schema::table('library_memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_branch_library_index');
        });

        Schema::table('book_copies', function (Blueprint $table) {
            $table->dropIndex('book_copies_location_library_index');
            $table->dropIndex('book_copies_branch_library_index');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_branch_library_index');
        });
    }

    private function replaceForeignKeys(): void
    {
        $this->dropSimpleForeignKeys();

        $this->addForeign('locations', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'cascade', 'cascade', 'locations_branch_library_fk');

        $this->addForeign('book_copies', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'cascade', 'cascade', 'book_copies_branch_library_fk');
        $this->addForeign('book_copies', ['location_id', 'library_id'], 'locations', ['id', 'library_id'], 'restrict', 'cascade', 'book_copies_location_library_fk');

        $this->addForeign('library_memberships', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'cascade', 'memberships_branch_library_fk');

        $this->addForeign('loans', ['book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'cascade', 'cascade', 'loans_book_copy_library_fk');
        $this->addForeign('loans', ['library_id', 'user_id'], 'library_memberships', ['library_id', 'user_id'], 'cascade', 'cascade', 'loans_user_membership_fk');

        $this->addForeign('reservations', ['library_id', 'user_id'], 'library_memberships', ['library_id', 'user_id'], 'cascade', 'cascade', 'reservations_user_membership_fk');
        $this->addForeign('reservations', ['branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'cascade', 'reservations_branch_library_fk');
        $this->addForeign('reservations', ['pickup_branch_id', 'library_id'], 'branches', ['id', 'library_id'], 'restrict', 'cascade', 'reservations_pickup_branch_library_fk');
        $this->addForeign('reservations', ['assigned_book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'restrict', 'cascade', 'reservations_assigned_copy_library_fk');

        $this->addForeign('scan_logs', ['book_copy_id', 'library_id'], 'book_copies', ['id', 'library_id'], 'restrict', 'cascade', 'scan_logs_book_copy_library_fk');
    }

    private function dropCompositeForeignKeys(): void
    {
        foreach ([
            ['scan_logs', 'scan_logs_user_membership_fk'],
            ['scan_logs', 'scan_logs_book_copy_library_fk'],
            ['reservations', 'reservations_assigned_copy_library_fk'],
            ['reservations', 'reservations_pickup_branch_library_fk'],
            ['reservations', 'reservations_branch_library_fk'],
            ['reservations', 'reservations_user_membership_fk'],
            ['loans', 'loans_user_membership_fk'],
            ['loans', 'loans_book_copy_library_fk'],
            ['library_memberships', 'memberships_branch_library_fk'],
            ['book_copies', 'book_copies_location_library_fk'],
            ['book_copies', 'book_copies_branch_library_fk'],
            ['locations', 'locations_branch_library_fk'],
        ] as [$table, $constraint]) {
            $this->dropForeignIfExists($table, $constraint);
        }
    }

    private function dropSimpleForeignKeys(): void
    {
        foreach ([
            ['locations', 'locations_branch_id_foreign'],
            ['book_copies', 'book_copies_branch_id_foreign'],
            ['book_copies', 'book_copies_location_id_foreign'],
            ['library_memberships', 'library_memberships_branch_id_foreign'],
            ['loans', 'loans_book_copy_id_foreign'],
            ['loans', 'loans_user_id_foreign'],
            ['loans', 'loans_issued_by_foreign'],
            ['loans', 'loans_received_by_foreign'],
            ['reservations', 'reservations_user_id_foreign'],
            ['reservations', 'reservations_branch_id_foreign'],
            ['reservations', 'reservations_pickup_branch_id_foreign'],
            ['reservations', 'reservations_assigned_book_copy_id_foreign'],
            ['scan_logs', 'scan_logs_book_copy_id_foreign'],
            ['scan_logs', 'scan_logs_user_id_foreign'],
        ] as [$table, $constraint]) {
            $this->dropForeignIfExists($table, $constraint);
        }
    }

    private function restoreSimpleForeignKeys(): void
    {
        $this->addForeign('locations', ['branch_id'], 'branches', ['id'], 'cascade', 'cascade', 'locations_branch_id_foreign');

        $this->addForeign('book_copies', ['branch_id'], 'branches', ['id'], 'cascade', 'cascade', 'book_copies_branch_id_foreign');
        $this->addForeign('book_copies', ['location_id'], 'locations', ['id'], 'set null', 'cascade', 'book_copies_location_id_foreign');

        $this->addForeign('library_memberships', ['branch_id'], 'branches', ['id'], 'set null', 'cascade', 'library_memberships_branch_id_foreign');

        $this->addForeign('loans', ['book_copy_id'], 'book_copies', ['id'], 'cascade', 'cascade', 'loans_book_copy_id_foreign');
        $this->addForeign('loans', ['user_id'], 'users', ['id'], 'cascade', 'cascade', 'loans_user_id_foreign');
        $this->addForeign('loans', ['issued_by'], 'users', ['id'], 'set null', 'cascade', 'loans_issued_by_foreign');
        $this->addForeign('loans', ['received_by'], 'users', ['id'], 'set null', 'cascade', 'loans_received_by_foreign');

        $this->addForeign('reservations', ['user_id'], 'users', ['id'], 'cascade', 'cascade', 'reservations_user_id_foreign');
        $this->addForeign('reservations', ['branch_id'], 'branches', ['id'], 'set null', 'cascade', 'reservations_branch_id_foreign');
        $this->addForeign('reservations', ['pickup_branch_id'], 'branches', ['id'], 'set null', 'cascade', 'reservations_pickup_branch_id_foreign');
        $this->addForeign('reservations', ['assigned_book_copy_id'], 'book_copies', ['id'], 'restrict', 'cascade', 'reservations_assigned_book_copy_id_foreign');

        $this->addForeign('scan_logs', ['book_copy_id'], 'book_copies', ['id'], 'set null', 'cascade', 'scan_logs_book_copy_id_foreign');
        $this->addForeign('scan_logs', ['user_id'], 'users', ['id'], 'set null', 'cascade', 'scan_logs_user_id_foreign');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $references
     */
    private function addForeign(
        string $table,
        array $columns,
        string $foreignTable,
        array $references,
        string $onDelete,
        string $onUpdate,
        string $name
    ): void {
        $columnList = implode(', ', array_map(fn ($column) => "`{$column}`", $columns));
        $referenceList = implode(', ', array_map(fn ($column) => "`{$column}`", $references));

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY ({$columnList}) REFERENCES `{$foreignTable}` ({$referenceList}) ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
