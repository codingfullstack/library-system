<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Support\Tenancy\TenantIntegrityAuditor;
use Illuminate\Support\Facades\DB;

it('migration preflight aborts instead of silently fixing bad tenant data', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    DB::table('book_copies')
        ->where('id', $copy->id)
        ->update(['branch_id' => $otherBranch->id]);

    $migration = require base_path('database/migrations/2026_07_28_000000_enforce_tenant_ownership_invariants.php');

    expect(fn () => $migration->up())
        ->toThrow(RuntimeException::class, 'book_copies#'.$copy->id);

    expect(DB::table('book_copies')->where('id', $copy->id)->value('branch_id'))
        ->toBe($otherBranch->id);
    expect(app(TenantIntegrityAuditor::class)->violations()->first()->type)
        ->toBe('branch_tenant_mismatch');
});
