<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns dashboard summary counts for the active library', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $copies = BookCopy::factory()->count(3)->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copies[0]->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => now()->addDay(),
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copies[1]->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => now()->subDay(),
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 3)
        ->assertJsonPath('summary.active_loans_count', 2)
        ->assertJsonPath('summary.active_reservations_count', 1)
        ->assertJsonPath('summary.overdue_loans_count', 1);
});
