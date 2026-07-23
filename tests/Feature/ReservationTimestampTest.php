<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;

function reservationTimestampFixture(): array
{
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);

    return [$library, $branch, $book, $copy, $staff, $member];
}

it('does not change reserved_at when a waiting reservation becomes ready', function () {
    [$library, , $book, , , $member] = reservationTimestampFixture();

    $reservedAt = now()->subDays(3)->setSecond(0)->setMicrosecond(0);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $reservedAt,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservation->fresh()->reserved_at->equalTo($reservedAt))->toBeTrue();
});

it('does not change reserved_at when a reservation is cancelled', function () {
    [$library, , $book, , $staff, $member] = reservationTimestampFixture();

    $reservedAt = now()->subDays(4)->setSecond(0)->setMicrosecond(0);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $reservedAt,
    ]);

    app(CancelReservationAction::class)->handle($staff, $reservation, 'Test cancellation.');

    expect($reservation->fresh()->reserved_at->equalTo($reservedAt))->toBeTrue();
});

it('does not change reserved_at when a ready reservation expires', function () {
    [$library, $branch, $book, $copy, , $member] = reservationTimestampFixture();

    $reservedAt = now()->subDays(5)->setSecond(0)->setMicrosecond(0);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_READY,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copy->id,
        'reserved_at' => $reservedAt,
        'ready_at' => now()->subDays(2),
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('reservations:expire')->assertSuccessful();

    expect($reservation->fresh()->reserved_at->equalTo($reservedAt))->toBeTrue();
});

it('does not change reserved_at when a ready reservation is fulfilled', function () {
    [$library, $branch, $book, $copy, $staff, $member] = reservationTimestampFixture();

    $reservedAt = now()->subDays(6)->setSecond(0)->setMicrosecond(0);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_READY,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copy->id,
        'reserved_at' => $reservedAt,
        'ready_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
    ]);

    expect($reservation->fresh()->reserved_at->equalTo($reservedAt))->toBeTrue();
});

it('does not change reserved_at when other reservation fields change', function () {
    [$library, , $book, , , $member] = reservationTimestampFixture();

    $reservedAt = now()->subDays(7)->setSecond(0)->setMicrosecond(0);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $reservedAt,
    ]);

    $reservation->update(['notes' => 'Technical metadata update.']);

    expect($reservation->fresh()->reserved_at->equalTo($reservedAt))->toBeTrue();
});
