<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function capabilityParityAssignStaffBranch(User $staff, Library $library, ?Branch $branch): void
{
    $staff->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $branch?->id]);
}

function capabilityParityCopy(Library $library, Book $book, Branch $branch, string $status, string $inventoryCode): BookCopy
{
    return BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'inventory_code' => $inventoryCode,
        'status' => $status,
    ]);
}

it('keeps loan return capability aligned with the return endpoint', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = capabilityParityCopy($library, $book, $branch, BookCopy::STATUS_LOANED, 'AUTH-RETURN-001');

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    $this->actingAs($member)
        ->getJson('/api/auth/loans/active')
        ->assertOk()
        ->assertJsonPath('data.0.can_return', false);

    $this->actingAs($member)
        ->postJson("/api/auth/book-copies/{$copy->id}/return")
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson('/api/auth/loans/active')
        ->assertOk()
        ->assertJsonPath('data.0.can_return', true);

    $this->actingAs($admin)
        ->postJson("/api/auth/book-copies/{$copy->id}/return")
        ->assertOk();
});

it('keeps staff branch copy capabilities aligned with borrow endpoints', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $ownCopy = capabilityParityCopy($library, $book, $ownBranch, BookCopy::STATUS_AVAILABLE, 'AUTH-BORROW-001');
    $otherCopy = capabilityParityCopy($library, $book, $otherBranch, BookCopy::STATUS_AVAILABLE, 'AUTH-BORROW-002');

    capabilityParityAssignStaffBranch($staff, $library, $ownBranch);

    $response = $this->actingAs($staff)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk();

    $copies = collect($response->json('book_copies'))->keyBy('id');

    expect($copies[$ownCopy->id]['can_borrow'])->toBeTrue()
        ->and($copies[$otherCopy->id]['can_borrow'])->toBeFalse();

    $this->actingAs($staff)
        ->postJson("/api/auth/book-copies/{$otherCopy->id}/borrow", [
            'user_id' => $member->id,
            'due_at' => now()->addDays(14)->toDateString(),
            'no_due_date' => false,
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->postJson("/api/auth/book-copies/{$ownCopy->id}/borrow", [
            'user_id' => $member->id,
            'due_at' => now()->addDays(14)->toDateString(),
            'no_due_date' => false,
        ])
        ->assertOk();
});

it('denies staff without an assigned branch both capability and endpoint access', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = capabilityParityCopy($library, $book, $branch, BookCopy::STATUS_AVAILABLE, 'AUTH-NOBRANCH-001');

    capabilityParityAssignStaffBranch($staff, $library, null);

    $this->actingAs($staff)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->assertJsonPath('book_copies.0.can_borrow', false);

    $this->actingAs($staff)
        ->postJson("/api/auth/book-copies/{$copy->id}/borrow", [
            'user_id' => $member->id,
            'due_at' => now()->addDays(14)->toDateString(),
            'no_due_date' => false,
        ])
        ->assertForbidden();
});

it('keeps member reservation capability aligned with create reservation validation', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    capabilityParityCopy($library, $book, $branch, BookCopy::STATUS_LOANED, 'AUTH-RESERVE-001');

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'status' => Reservation::STATUS_WAITING,
    ]);

    $this->actingAs($member)
        ->getJson("/api/auth/books/{$book->id}")
        ->assertOk()
        ->assertJsonPath('can_reserve', false);

    $this->actingAs($member)
        ->postJson('/api/auth/reservations', [
            'book_id' => $book->id,
            'scope' => Reservation::SCOPE_LIBRARY,
        ])
        ->assertUnprocessable();
});

it('blocks cross-library copy actions at server side route binding', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $otherAdmin = User::factory()->admin()->create(['library_id' => $otherLibrary->id]);
    $book = Book::factory()->create();
    $copy = capabilityParityCopy($library, $book, $branch, BookCopy::STATUS_AVAILABLE, 'AUTH-IDOR-001');

    $this->actingAs($otherAdmin)
        ->withHeader('X-Library-Id', (string) $otherLibrary->id)
        ->getJson("/api/auth/book-copies/{$copy->id}")
        ->assertNotFound();

    $this->actingAs($otherAdmin)
        ->withHeader('X-Library-Id', (string) $otherLibrary->id)
        ->postJson("/api/auth/book-copies/{$copy->id}/return")
        ->assertNotFound();
});

it('keeps web export visibility aligned with export endpoint authorization', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->actingAs($member)
        ->get(route('books.index'))
        ->assertOk()
        ->assertDontSee(route('exports.list', ['resource' => 'books']), false);

    $this->actingAs($member)
        ->get(route('exports.list', ['resource' => 'books']))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('books.index'))
        ->assertOk()
        ->assertSee(route('exports.list', ['resource' => 'books']), false);
});
