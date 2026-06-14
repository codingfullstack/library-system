<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function branchCopy(Book $book, Library $library, Branch $branch, string $status = BookCopy::STATUS_AVAILABLE): BookCopy
{
    $location = Location::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
    ]);

    return BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => $status,
    ]);
}

function assignStaffBranch(User $staff, Library $library, Branch $branch): void
{
    $staff->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $branch->id]);
}

it('lets staff see another branch copy in the same library', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($staff, $library, $ownBranch);

    $book = Book::factory()->create();
    $otherCopy = branchCopy($book, $library, $otherBranch);

    $this->actingAs($staff)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $otherCopy->id,
            'inventory_code' => $otherCopy->inventory_code,
        ])
        ->assertJsonPath('book_copies.0.branch.id', $otherBranch->id);
});

it('lets staff see other branch copies without active loan identity details', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($staff, $library, $ownBranch);

    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $issuer = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($issuer, $library, $otherBranch);

    $book = Book::factory()->create();
    $otherCopy = branchCopy($book, $library, $otherBranch, BookCopy::STATUS_LOANED);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $member->id,
        'issued_by' => $issuer->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $this->actingAs($staff)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $otherCopy->id,
            'status' => BookCopy::STATUS_LOANED,
        ])
        ->assertJsonPath('book_copies.0.branch.id', $otherBranch->id)
        ->assertJsonPath('book_copies.0.active_loan', null)
        ->assertJsonPath('book_copies.0.can_borrow', false)
        ->assertJsonPath('book_copies.0.can_return', false)
        ->assertJsonMissing([
            'email' => $member->email,
        ])
        ->assertJsonMissing([
            'name' => $member->name,
        ]);
});

it('shows branch restriction hover titles for disabled copy actions', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($staff, $library, $ownBranch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $otherCopy = branchCopy($book, $library, $otherBranch, BookCopy::STATUS_LOANED);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Negalima išduoti: egzempliorius priklauso kitam filialui.', false)
        ->assertSee('Negalima priimti grąžinimo: egzempliorius priklauso kitam filialui.', false);
});

it('blocks staff from borrowing another branch copy', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($staff, $library, $ownBranch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = branchCopy($book, $library, $otherBranch);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
    ]))->toThrow(ValidationException::class);

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('lets staff borrow own branch copy', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    assignStaffBranch($staff, $library, $branch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = branchCopy($book, $library, $branch);

    app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
    ]);

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_LOANED)
        ->and(Loan::query()->where('book_copy_id', $copy->id)->where('user_id', $member->id)->exists())->toBeTrue();
});

it('lets admin manage copies in all branches', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = branchCopy($book, $library, $branch);

    app(BorrowBookCopyAction::class)->handle($admin, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
    ]);

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_LOANED);
});
