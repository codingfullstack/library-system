<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;

function seedDashboardSummaryFixture(): array
{
    $libraryX = Library::factory()->create(['code' => 'LIB-X']);
    $libraryY = Library::factory()->create(['code' => 'LIB-Y']);
    $branchX1 = Branch::factory()->create(['library_id' => $libraryX->id, 'code' => 'LIB-X-BR-01']);
    $branchX2 = Branch::factory()->create(['library_id' => $libraryX->id, 'code' => 'LIB-X-BR-02']);
    $branchY1 = Branch::factory()->create(['library_id' => $libraryY->id, 'code' => 'LIB-Y-BR-01']);
    $book = Book::factory()->create();

    $memberX = memberInLibrary($libraryX);
    $memberY = memberInLibrary($libraryY);

    $x1Available = BookCopy::factory()->count(2)->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'branch_id' => $branchX1->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $x1Loaned = BookCopy::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'branch_id' => $branchX1->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    $x2Copies = BookCopy::factory()->count(4)->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'branch_id' => $branchX2->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $yCopies = BookCopy::factory()->count(5)->create([
        'library_id' => $libraryY->id,
        'book_id' => $book->id,
        'branch_id' => $branchY1->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Loan::factory()->create([
        'library_id' => $libraryX->id,
        'book_copy_id' => $x1Loaned->id,
        'user_id' => $memberX->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => now()->addDay(),
    ]);
    Loan::factory()->create([
        'library_id' => $libraryX->id,
        'book_copy_id' => $x2Copies->first()->id,
        'user_id' => $memberX->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => now()->subDay(),
    ]);
    Loan::factory()->create([
        'library_id' => $libraryY->id,
        'book_copy_id' => $yCopies->first()->id,
        'user_id' => $memberY->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    Reservation::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'user_id' => $memberX->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'user_id' => $memberX->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchX1->id,
        'pickup_branch_id' => $branchX1->id,
        'assigned_book_copy_id' => $x1Available->first()->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now(),
        'ready_at' => now(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryX->id,
        'book_id' => $book->id,
        'user_id' => $memberX->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchX2->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryY->id,
        'book_id' => $book->id,
        'user_id' => $memberY->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);

    return compact('libraryX', 'libraryY', 'branchX1', 'branchX2', 'branchY1');
}

it('scopes dashboard summary to the active library for administrators', function () {
    ['libraryX' => $libraryX, 'libraryY' => $libraryY] = seedDashboardSummaryFixture();

    $this->actingAs(adminInLibrary($libraryX))
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 7)
        ->assertJsonPath('summary.available_book_copies_count', 4)
        ->assertJsonPath('summary.active_loans_count', 2)
        ->assertJsonPath('summary.active_reservations_count', 3)
        ->assertJsonPath('summary.overdue_loans_count', 1);

    $this->actingAs(adminInLibrary($libraryY))
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 5)
        ->assertJsonPath('summary.available_book_copies_count', 4)
        ->assertJsonPath('summary.active_loans_count', 1)
        ->assertJsonPath('summary.active_reservations_count', 1);
});

it('scopes dashboard summary to the staff assigned branch', function () {
    ['libraryX' => $libraryX, 'branchX1' => $branchX1, 'branchX2' => $branchX2] = seedDashboardSummaryFixture();

    $this->actingAs(staffInBranch($libraryX, $branchX1))
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 3)
        ->assertJsonPath('summary.available_book_copies_count', 1)
        ->assertJsonPath('summary.active_loans_count', 1)
        ->assertJsonPath('summary.active_reservations_count', 1);

    $this->actingAs(staffInBranch($libraryX, $branchX2))
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 4)
        ->assertJsonPath('summary.available_book_copies_count', 3)
        ->assertJsonPath('summary.active_loans_count', 1)
        ->assertJsonPath('summary.active_reservations_count', 1);
});

it('does not widen staff without an active branch to the whole library', function () {
    ['libraryX' => $libraryX, 'branchX1' => $branchX1] = seedDashboardSummaryFixture();
    $staff = staffInBranch($libraryX, $branchX1);
    $staff->libraryMemberships()->where('library_id', $libraryX->id)->update(['branch_id' => null]);

    $this->actingAs($staff->refresh())
        ->getJson('/api/auth/dashboard/summary')
        ->assertForbidden();
});

it('rejects inactive membership and unauthenticated dashboard summary requests', function () {
    $this->getJson('/api/auth/dashboard/summary')
        ->assertUnauthorized();

    ['libraryX' => $libraryX] = seedDashboardSummaryFixture();
    $admin = adminInLibrary($libraryX);
    $admin->libraryMemberships()->where('library_id', $libraryX->id)->update(['is_active' => false]);

    $this->actingAs($admin->refresh())
        ->getJson('/api/auth/dashboard/summary')
        ->assertForbidden();
});

it('returns only summary aggregates and no object lists', function () {
    ['libraryX' => $libraryX] = seedDashboardSummaryFixture();

    $this->actingAs(adminInLibrary($libraryX))
        ->getJson('/api/auth/dashboard/summary')
        ->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'book_copies_count',
                'available_book_copies_count',
                'active_loans_count',
                'active_reservations_count',
                'active_members_count',
                'overdue_loans_count',
            ],
        ])
        ->assertJsonMissingPath('popularBooks')
        ->assertJsonMissingPath('activityTimeline')
        ->assertJsonMissingPath('activeMembers');
});
