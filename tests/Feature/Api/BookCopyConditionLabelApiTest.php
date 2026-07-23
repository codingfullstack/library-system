<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns canonical condition labels without changing the condition status contract', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('book_copies.0.condition_status', BookCopy::CONDITION_DAMAGED)
        ->assertJsonPath('book_copies.0.condition_label', BookCopy::conditionLabelFor(BookCopy::CONDITION_DAMAGED));
});
