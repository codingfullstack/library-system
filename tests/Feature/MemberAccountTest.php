<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('shows member dashboard with own summary', function () {
    $library = Library::factory()->create(['name' => 'Nario biblioteka']);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Mano knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'due_at' => now()->addDays(7),
    ]);

    $otherCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $otherCopy->id,
        'user_id' => $otherMember->id,
        'status' => 'vėluoja',
        'returned_at' => null,
        'due_at' => now()->subDays(2),
    ]);

    $member->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'loan_overdue',
        'data' => [
            'kind' => 'loan_overdue',
            'title' => 'Vėluojate grąžinti knygą',
            'message' => 'Primename apie termina.',
            'url' => route('notifications.index'),
            'created_at' => now()->toIso8601String(),
        ],
    ]);

    $this->actingAs($member)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Mano paskyra')
        ->assertSee('Aktyvios išduotos knygos')
        ->assertSee('Nario biblioteka')
        ->assertSee('Vėluojate grąžinti knygą')
        ->assertSee('Primename apie termina.');
});

it('shows only active member loans in member account area', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $bookA = Book::factory()->create(['title' => 'Aktyvi knyga']);
    $bookB = Book::factory()->create(['title' => 'Grąžinta knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $activeCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $bookA->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $returnedCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $bookB->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $activeCopy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
        'due_at' => now()->addDays(5),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $returnedCopy->id,
        'user_id' => $member->id,
        'status' => 'grąžinta',
        'returned_at' => now()->subDay(),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)
        ->get(route('loans.index'))
        ->assertOk()
        ->assertSee('Mano išduotos knygos')
        ->assertSee('Aktyvi knyga')
        ->assertDontSee('Grąžinta knyga');
});

it('shows member book page without copy management details', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create([
        'title' => 'Nario matoma knyga',
        'description' => 'Pilnas aprašymas nariui.',
    ]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($member)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Pilnas aprašymas nariui.')
        ->assertDontSee('Knygos kopijos')
        ->assertDontSee('Inventoriaus kodas');
});

it('shows books from all member libraries with library information', function () {
    $firstLibrary = Library::factory()->create([
        'name' => 'Pirma biblioteka',
        'address' => 'Pirma g. 1',
        'city' => 'Vilnius',
    ]);
    $secondLibrary = Library::factory()->create([
        'name' => 'Antra biblioteka',
        'address' => 'Antra g. 2',
        'city' => 'Kaunas',
    ]);
    $otherLibrary = Library::factory()->create(['name' => 'Svetima biblioteka']);
    $member = User::factory()->member()->create(['library_id' => $firstLibrary->id]);

    LibraryMembership::factory()->member()->create([
        'library_id' => $secondLibrary->id,
        'user_id' => $member->id,
        'membership_number' => 'SECOND-MEM-010',
    ]);

    $firstBook = Book::factory()->create(['title' => 'Pirmos bibliotekos knyga']);
    $secondBook = Book::factory()->create(['title' => 'Antros bibliotekos knyga']);
    $otherBook = Book::factory()->create(['title' => 'Svetima knyga']);

    BookCopy::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $firstBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $secondLibrary->id,
        'book_id' => $secondBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $otherBook->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $this->actingAs($member)
        ->withSession(['active_library_id' => $firstLibrary->id])
        ->get(route('books.index'))
        ->assertOk()
        ->assertSee('Pirmos bibliotekos knyga')
        ->assertSee('Antros bibliotekos knyga')
        ->assertSee('Pirma biblioteka')
        ->assertSee('Pirma g. 1, Vilnius')
        ->assertSee('Antra biblioteka')
        ->assertSee('Antra g. 2, Kaunas')
        ->assertDontSee('Svetima knyga')
        ->assertDontSee('Svetima biblioteka');
});
