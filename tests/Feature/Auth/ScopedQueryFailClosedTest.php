<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Loans\GetActiveLibraryLoansQuery;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects library scoped loan and reservation queries without a library id', function () {
    $admin = User::factory()->admin()->create();

    expect(fn () => app(GetActiveLibraryLoansQuery::class)->handle($admin, ['per_page' => 10]))
        ->toThrow(LogicException::class, 'Library-scoped loan query requires a library ID.')
        ->and(fn () => app(GetLibraryReservationsQuery::class)->handle($admin, ['per_page' => 10]))
        ->toThrow(LogicException::class, 'Library-scoped reservation query requires a library ID.');
});

it('allows superadmin global loan and reservation queries only through explicit global scope', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $member = memberInLibrary($library);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);
    $superAdmin = superAdmin();

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
    ]);

    expect(fn () => app(GetActiveLibraryLoansQuery::class)->handle($superAdmin, ['per_page' => 10]))
        ->toThrow(LogicException::class, 'Library-scoped loan query requires a library ID.')
        ->and(fn () => app(GetLibraryReservationsQuery::class)->handle($superAdmin, ['per_page' => 10]))
        ->toThrow(LogicException::class, 'Library-scoped reservation query requires a library ID.');

    expect(app(GetActiveLibraryLoansQuery::class)->handle($superAdmin, ['scope' => 'global', 'per_page' => 10])->total())
        ->toBe(1)
        ->and(app(GetLibraryReservationsQuery::class)->handle($superAdmin, ['scope' => 'global', 'per_page' => 10])->total())
        ->toBe(1);
});
