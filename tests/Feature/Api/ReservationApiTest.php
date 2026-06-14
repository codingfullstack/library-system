<?php

use App\Models\Book;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not return expired reserved reservations in the active api filter', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $expiredReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subDays(6),
        'expires_at' => now()->subDay(),
    ]);

    $activeReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($staff)
        ->getJson('/api/auth/reservations?status='.Reservation::STATUS_RESERVED)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('summary.active_count', 1)
        ->assertJsonPath('summary.expired_count', 0)
        ->assertJsonPath('data.0.id', $activeReservation->id);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->not
        ->toContain($expiredReservation->id);
});

it('accepts canonical reservation status constants in api filters', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $fulfilledReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_FULFILLED,
        'reserved_at' => now()->subDay(),
        'fulfilled_at' => now(),
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_CANCELLED,
        'reserved_at' => now(),
        'cancelled_at' => now(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations?status='.urlencode(Reservation::STATUS_FULFILLED))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('summary.fulfilled_count', 1)
        ->assertJsonPath('data.0.id', $fulfilledReservation->id)
        ->assertJsonPath('data.0.status_label', 'Įvykdyta');
});

it('labels expired reserved reservations as expired in api responses', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subDays(6),
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.status', Reservation::STATUS_RESERVED)
        ->assertJsonPath('data.0.status_label', 'Pasibaigusi')
        ->assertJsonPath('data.0.is_pending', false);
});

it('includes active reservations in the api book details response', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('reservations.0.id', $reservation->id)
        ->assertJsonPath('reservations.0.is_pending', true);
});

it('orders api reservations by reservation date descending', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $olderReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_CANCELLED,
        'reserved_at' => now()->subYear(),
        'created_at' => now(),
    ]);

    $newerReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now()->subMonth(),
        'created_at' => now()->subYear(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newerReservation->id)
        ->assertJsonPath('data.1.id', $olderReservation->id);
});
