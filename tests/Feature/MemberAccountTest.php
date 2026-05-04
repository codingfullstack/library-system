<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'status' => 'active',
        'returned_at' => null,
        'due_at' => now()->addDays(7),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $otherMember->id,
        'status' => 'overdue',
        'returned_at' => null,
        'due_at' => now()->subDays(2),
    ]);

    $member->notifications()->create([
        'type' => 'loan_overdue',
        'title' => 'Veluojate grazinti knyga',
        'message' => 'Primename apie termina.',
    ]);

    $this->actingAs($member)
        ->get(route('account.dashboard'))
        ->assertOk()
        ->assertSee('Mano paskyra')
        ->assertSee('Aktyvios isduotos knygos')
        ->assertSee('Nario biblioteka');
});

it('shows only active member loans in member account area', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $bookA = Book::factory()->create(['title' => 'Aktyvi knyga']);
    $bookB = Book::factory()->create(['title' => 'Grazinta knyga']);
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
        'status' => 'active',
        'returned_at' => null,
        'due_at' => now()->addDays(5),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $returnedCopy->id,
        'user_id' => $member->id,
        'status' => 'returned',
        'returned_at' => now()->subDay(),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)
        ->get(route('loans.index'))
        ->assertOk()
        ->assertSee('Mano isduotos knygos')
        ->assertSee('Aktyvi knyga')
        ->assertDontSee('Grazinta knyga');
});

it('shows member book page without copy management details', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create([
        'title' => 'Nario matoma knyga',
        'description' => 'Pilnas aprasymas nariui.',
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
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($member)
        ->get(route('books.show', $book))
        ->assertOk()
        ->assertSee('Pilnas aprasymas nariui.')
        ->assertDontSee('Knygos kopijos')
        ->assertDontSee('Inventoriaus kodas');
});
