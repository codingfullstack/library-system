<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

it('migrates legacy copy lifecycle and damaged condition into the canonical axes', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();

    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA ignore_check_constraints = ON');
    }

    $legacyDamagedId = DB::table('book_copies')->insertGetId([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LEGACY-AXES-001',
        'qr_code' => 'QR-LEGACY-AXES-001',
        'barcode' => null,
        'status' => 'laisva',
        'condition_status' => BookCopy::CONDITION_DAMAGED,
        'acquired_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $legacyLoanedId = DB::table('book_copies')->insertGetId([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LEGACY-AXES-002',
        'qr_code' => 'QR-LEGACY-AXES-002',
        'barcode' => null,
        'status' => BookCopy::LEGACY_STATUS_LOANED,
        'condition_status' => BookCopy::CONDITION_GOOD,
        'acquired_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $withdrawnDamagedId = DB::table('book_copies')->insertGetId([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'LEGACY-AXES-003',
        'qr_code' => 'QR-LEGACY-AXES-003',
        'barcode' => null,
        'status' => BookCopy::STATUS_WITHDRAWN,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
        'acquired_at' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_11_120000_refactor_book_copy_lifecycle_and_condition_axes.php');
    $migration->up();
    $lifecycleMigration = require database_path('migrations/2026_08_11_130000_add_lifecycle_status_to_book_copies.php');
    $lifecycleMigration->up();

    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA ignore_check_constraints = OFF');
    }

    $this->assertDatabaseHas('book_copies', [
        'id' => $legacyDamagedId,
        'status' => BookCopy::STATUS_MAINTENANCE,
        'lifecycle_status' => BookCopy::STATUS_MAINTENANCE,
        'condition_status' => BookCopy::CONDITION_WORN,
    ]);

    $this->assertDatabaseHas('book_copies', [
        'id' => $legacyLoanedId,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    $this->assertDatabaseHas('book_copies', [
        'id' => $withdrawnDamagedId,
        'status' => BookCopy::STATUS_WITHDRAWN,
        'lifecycle_status' => BookCopy::STATUS_WITHDRAWN,
        'condition_status' => BookCopy::CONDITION_WORN,
    ]);

    expect(BookCopy::query()->where('condition_status', BookCopy::CONDITION_DAMAGED)->count())->toBe(0)
        ->and(BookCopy::query()->where('status', BookCopy::LEGACY_STATUS_LOANED)->count())->toBe(0)
        ->and(BookCopy::query()->where('status', 'laisva')->count())->toBe(0)
        ->and(BookCopy::query()->whereNull('lifecycle_status')->count())->toBe(0);
});
