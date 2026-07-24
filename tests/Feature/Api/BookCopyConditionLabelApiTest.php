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

it('returns lifecycle status and physical condition as separate labeled fields', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_DAMAGED,
    ]);

    $response = $this->actingAs($staff)
        ->getJson("/api/auth/book-copies/{$copy->id}")
        ->assertOk()
        ->assertJsonPath('status', BookCopy::STATUS_AVAILABLE)
        ->assertJsonPath('status_label', BookCopy::statusLabels()[BookCopy::STATUS_AVAILABLE])
        ->assertJsonPath('condition_status', BookCopy::CONDITION_DAMAGED)
        ->assertJsonPath('condition_label', BookCopy::conditionLabels()[BookCopy::CONDITION_DAMAGED]);

    expect($response->json('available_lifecycle_transitions'))
        ->not->toContain(BookCopy::CONDITION_DAMAGED);
});
