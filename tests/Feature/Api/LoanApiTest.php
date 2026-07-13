<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns canonical loan status fields from the api', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDay(),
        'due_at' => now()->addDays(3),
        'returned_at' => null,
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/loans/active?status='.Loan::STATUS_ACTIVE)
        ->assertOk()
        ->assertJsonPath('0.id', $loan->id)
        ->assertJsonPath('0.status_label', 'Aktyvi')
        ->assertJsonPath('0.is_overdue', false)
        ->assertJsonPath('0.is_due_soon', true)
        ->assertJsonPath('0.overdue_days', 0);
});
