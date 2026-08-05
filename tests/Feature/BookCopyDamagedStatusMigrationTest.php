<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCopyStatusHistory;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('migrates legacy damaged lifecycle status into physical condition', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    if (DB::getDriverName() === 'mysql') {
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $enumValues = collect([
            BookCopy::STATUS_AVAILABLE,
            BookCopy::STATUS_LOANED,
            BookCopy::STATUS_LOST,
            BookCopy::STATUS_MAINTENANCE,
            BookCopy::STATUS_WITHDRAWN,
            BookCopy::LEGACY_STATUS_DAMAGED,
        ])
            ->map(fn (string $status) => DB::getPdo()->quote($status))
            ->implode(', ');

        DB::statement("
            ALTER TABLE book_copies
            MODIFY status ENUM({$enumValues}) NOT NULL DEFAULT ".DB::getPdo()->quote(BookCopy::STATUS_AVAILABLE)
        );
    } elseif (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA ignore_check_constraints = ON');
    }

    $availableLegacyId = DB::table('book_copies')->insertGetId([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LEGACY-DAMAGED-001',
        'qr_code' => 'QR-LEGACY-DAMAGED-001',
        'barcode' => null,
        'status' => BookCopy::LEGACY_STATUS_DAMAGED,
        'condition_status' => BookCopy::CONDITION_GOOD,
        'acquired_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $loanedLegacyId = DB::table('book_copies')->insertGetId([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LEGACY-DAMAGED-002',
        'qr_code' => 'QR-LEGACY-DAMAGED-002',
        'barcode' => null,
        'status' => BookCopy::LEGACY_STATUS_DAMAGED,
        'condition_status' => BookCopy::CONDITION_WORN,
        'acquired_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $loanedLegacyId,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    BookCopyStatusHistory::query()->create([
        'book_copy_id' => $availableLegacyId,
        'changed_by' => null,
        'from_status' => BookCopy::STATUS_AVAILABLE,
        'to_status' => BookCopy::LEGACY_STATUS_DAMAGED,
        'reason_code' => 'marked_damaged',
        'reason_notes' => 'Legacy status history.',
        'changed_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_07_23_050000_migrate_damaged_book_copy_status_to_condition.php');
    $migration->up();

    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA ignore_check_constraints = OFF');
    }

    $this->assertDatabaseHas('book_copies', [
        'id' => $availableLegacyId,
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    $this->assertDatabaseHas('book_copies', [
        'id' => $loanedLegacyId,
        'status' => BookCopy::STATUS_LOANED,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    $this->assertDatabaseHas('book_copy_status_histories', [
        'book_copy_id' => $availableLegacyId,
        'from_status' => BookCopy::STATUS_AVAILABLE,
        'to_status' => BookCopy::STATUS_AVAILABLE,
        'reason_code' => 'marked_damaged',
    ]);

    if (DB::getDriverName() === 'mysql' && DB::transactionLevel() === 0) {
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
    }
});
