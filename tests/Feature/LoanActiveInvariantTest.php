<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('mysql', 'mariadb', 'database-invariants', 'concurrency');

function loanInvariantFixture(): array
{
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
    ]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    return [$library, $copy, $staff, $member];
}

it('creates a normal loan through the borrow action', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();

    $result = app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Invariant test.',
    ]);

    expect($result['loan'])->toBeInstanceOf(Loan::class)
        ->and($copy->fresh()->status)->toBe(BookCopy::STATUS_LOANED)
        ->and($copy->fresh()->activeLoan()->count())->toBe(1);
});

it('rejects two borrow operations for the same copy even when the second uses a stale model', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $copy->library_id]);
    $staleCopy = $copy->fresh();

    app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $staleCopy, [
        'user_id' => $otherMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(ValidationException::class);

    expect($copy->fresh()->loans()->active()->count())->toBe(1);
});

it('prevents bypassing application logic with a direct second active loan insert', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $copy->library_id]);

    Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    expect(fn () => Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $otherMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_OVERDUE,
        'returned_at' => null,
    ]))->toThrow(QueryException::class);
});

it('allows a new loan after the previous loan is returned', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $copy->library_id]);

    app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]);

    app(ReturnBookCopyAction::class)->handle($staff, $copy->fresh());

    $result = app(BorrowBookCopyAction::class)->handle($staff, $copy->fresh(), [
        'user_id' => $otherMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]);

    expect($result['loan'])->toBeInstanceOf(Loan::class)
        ->and($copy->fresh()->loans()->active()->count())->toBe(1)
        ->and($copy->fresh()->loans()->count())->toBe(2);
});

it('keeps the invariant when an active loan status changes to overdue', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $copy->library_id]);

    $loan = Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $loan->update(['status' => Loan::STATUS_OVERDUE]);

    expect(fn () => Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $otherMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]))->toThrow(QueryException::class);
});

it('prevents reactivating a returned loan when the copy already has an active loan', function () {
    [, $copy, $staff, $member] = loanInvariantFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $copy->library_id]);

    Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $returnedLoan = Loan::factory()->create([
        'library_id' => $copy->library_id,
        'book_copy_id' => $copy->id,
        'user_id' => $otherMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_RETURNED,
        'returned_at' => now()->subDay(),
    ]);

    expect(fn () => $returnedLoan->update([
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]))->toThrow(QueryException::class);
});
