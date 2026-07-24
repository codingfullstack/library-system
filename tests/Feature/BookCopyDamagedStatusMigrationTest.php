<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCopyStatusHistory;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('migrates legacy damaged lifecycle status into physical condition', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    Schema::disableForeignKeyConstraints();
    Schema::drop('book_copies');
    Schema::create('book_copies', function ($table) {
        $table->id();
        $table->unsignedBigInteger('library_id');
        $table->unsignedBigInteger('book_id');
        $table->unsignedBigInteger('branch_id');
        $table->unsignedBigInteger('location_id')->nullable();
        $table->string('inventory_code');
        $table->string('qr_code');
        $table->string('barcode')->nullable();
        $table->string('status');
        $table->string('condition_status')->default(BookCopy::CONDITION_GOOD);
        $table->date('acquired_at')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
    Schema::enableForeignKeyConstraints();

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
});
