<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;

function makeStaffBranchCopy(Book $book, Library $library, Branch $branch, string $status = BookCopy::STATUS_AVAILABLE): BookCopy
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

function makeBranchStaff(Library $library, Branch $branch): User
{
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $staff->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $branch->id]);

    return $staff;
}

it('hides other branch active loan sensitive fields in book details api', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = makeBranchStaff($library, $ownBranch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = makeStaffBranchCopy($book, $library, $otherBranch, BookCopy::STATUS_LOANED);

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => now()->addDays(7),
    ]);

    $this->actingAs($staff)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->assertJsonFragment([
            'id' => $copy->id,
            'status' => BookCopy::STATUS_LOANED,
        ])
        ->assertJsonPath('book_copies.0.active_loan', null)
        ->assertJsonMissing(['user_id' => $member->id])
        ->assertJsonMissing(['email' => $member->email])
        ->assertJsonMissing(['membership_number' => $member->membership_number]);
});

it('does not return other branch reservations to staff reservation api', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = makeBranchStaff($library, $ownBranch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $otherReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $otherBranch->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonMissing(['id' => $otherReservation->id])
        ->assertJsonMissing(['email' => $member->email])
        ->assertJsonMissing(['membership_number' => $member->membership_number]);
});

it('keeps own branch loan details visible to staff', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = makeBranchStaff($library, $branch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = makeStaffBranchCopy($book, $library, $branch, BookCopy::STATUS_LOANED);

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active')
        ->assertOk()
        ->assertJsonPath('data.0.id', $loan->id)
        ->assertJsonPath('data.0.user.id', $member->id)
        ->assertJsonPath('data.0.user.email', $member->email);
});

it('keeps other branch reader data out of reservation exports and dashboard member counts', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = makeBranchStaff($library, $ownBranch);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $otherBranch->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);

    $this->actingAs($staff)
        ->get(route('exports.list', ['resource' => 'reservations']))
        ->assertOk()
        ->assertDontSee($member->email, false)
        ->assertDontSee($member->membership_number, false);

    $this->actingAs($staff)
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.active_members_count', 0);
});
