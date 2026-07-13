<?php

use App\Livewire\Reservations\ReservationHistory;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reservationHistoryFixture(): array
{
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Centras']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas filialas']);
    $ownLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $ownBranch->id]);
    $otherLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $otherBranch->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $staff->activeLibraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $ownBranch->id]);

    session(['active_library_id' => $library->id]);

    return compact(
        'library',
        'ownBranch',
        'otherBranch',
        'ownLocation',
        'otherLocation',
        'staff',
        'admin',
        'member',
        'book'
    );
}

function pendingReservationForHistory(array $fixture, array $overrides = []): Reservation
{
    return Reservation::factory()->create(array_merge([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ], $overrides));
}

function copyForHistory(array $fixture, Branch $branch, Location $location, string $status = BookCopy::STATUS_AVAILABLE): BookCopy
{
    return BookCopy::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => $status,
    ]);
}

it('hides first-in-queue issue button for staff when own branch has no available copy', function () {
    $fixture = reservationHistoryFixture();
    pendingReservationForHistory($fixture);
    copyForHistory($fixture, $fixture['otherBranch'], $fixture['otherLocation']);

    Livewire::actingAs($fixture['staff'])
        ->test(ReservationHistory::class, ['book' => $fixture['book']])
        ->assertDontSee('issueFirstInQueue', false)
        ->assertSee('Jūsų filiale nėra laisvos kopijos išdavimui.');
});

it('prevents staff from issuing an available copy from another branch', function () {
    $fixture = reservationHistoryFixture();
    $reservation = pendingReservationForHistory($fixture);
    $otherCopy = copyForHistory($fixture, $fixture['otherBranch'], $fixture['otherLocation']);

    Livewire::actingAs($fixture['staff'])
        ->test(ReservationHistory::class, ['book' => $fixture['book']])
        ->call('issueFirstInQueue')
        ->assertNoRedirect()
        ->assertHasErrors(['reservation']);

    expect(Loan::query()->count())->toBe(0)
        ->and($otherCopy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE)
        ->and($reservation->fresh()->status)->toBe(Reservation::STATUS_RESERVED);
});

it('allows admin to issue an available copy from the active library', function () {
    $fixture = reservationHistoryFixture();
    $reservation = pendingReservationForHistory($fixture);
    $copy = copyForHistory($fixture, $fixture['otherBranch'], $fixture['otherLocation']);

    Livewire::actingAs($fixture['admin'])
        ->test(ReservationHistory::class, ['book' => $fixture['book']])
        ->call('issueFirstInQueue')
        ->assertRedirect(route('books.show', $fixture['book']));

    expect(Loan::query()
        ->where('book_copy_id', $copy->id)
        ->where('user_id', $fixture['member']->id)
        ->exists())->toBeTrue()
        ->and($copy->fresh()->status)->toBe(BookCopy::STATUS_LOANED)
        ->and($reservation->fresh()->status)->toBe(Reservation::STATUS_FULFILLED);
});

it('shows a validation error instead of crashing when no available copy exists', function () {
    $fixture = reservationHistoryFixture();
    pendingReservationForHistory($fixture);
    copyForHistory($fixture, $fixture['ownBranch'], $fixture['ownLocation'], BookCopy::STATUS_LOANED);

    Livewire::actingAs($fixture['staff'])
        ->test(ReservationHistory::class, ['book' => $fixture['book']])
        ->call('issueFirstInQueue')
        ->assertNoRedirect()
        ->assertHasErrors(['reservation']);

    expect(Loan::query()->count())->toBe(0);
});
