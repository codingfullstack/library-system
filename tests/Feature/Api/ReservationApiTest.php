<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not return expired reservations in the active api filter', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $expiredReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now()->subDays(6),
        'ready_at' => now()->subDays(5),
        'expires_at' => now()->subDay(),
    ]);

    $activeReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
    ]);

    $response = $this->actingAs($staff)
        ->getJson('/api/auth/reservations?status='.Reservation::STATUS_WAITING)
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('summary.active_count', 1)
        ->assertJsonPath('summary.expired_count', 0)
        ->assertJsonPath('data.0.id', $activeReservation->id)
        ->assertJsonPath('data.0.queue_position', 1)
        ->assertJsonPath('data.0.queue_size', 1)
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'summary',
        ]);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->not
        ->toContain($expiredReservation->id);
});

it('returns waiting and ready reservations for the active api filter', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $readyMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $waitingReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
    ]);
    $readyReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $readyMember->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subHour(),
        'ready_at' => now()->subMinutes(30),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $expiredReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now(),
    ]);

    $response = $this->actingAs($staff)
        ->getJson('/api/auth/reservations?status=active')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('summary.active_count', 2);

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($waitingReservation->id, $readyReservation->id)
        ->not->toContain($expiredReservation->id);
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

it('labels explicitly expired reservations as expired in api responses', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now()->subDays(6),
        'ready_at' => now()->subDays(5),
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.status', Reservation::STATUS_EXPIRED)
        ->assertJsonPath('data.0.status_label', 'Pasibaigusi')
        ->assertJsonPath('data.0.is_pending', false);
});

it('returns pickup branch from pickup_branch_id in api responses', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $requestedBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Uzsakytas filialas']);
    $pickupBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Centrinis filialas']);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $requestedBranch->id,
        'pickup_branch_id' => $pickupBranch->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subDay(),
        'ready_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.pickup_branch.id', $pickupBranch->id)
        ->assertJsonPath('data.0.pickup_branch.name', 'Centrinis filialas')
        ->assertJsonPath('data.0.branch.id', $requestedBranch->id)
        ->assertJsonPath('data.0.queue_position', null)
        ->assertJsonPath('data.0.queue_size', null);
});

it('reports ready reservations consistently across reservation book and copy endpoints', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Atsiėmimo filialas']);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copy->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subDay(),
        'ready_at' => now()->subHour(),
        'expires_at' => now()->addDays(7),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.status', Reservation::STATUS_READY)
        ->assertJsonPath('data.0.status_label', 'Paruošta atsiimti')
        ->assertJsonPath('data.0.is_active', true)
        ->assertJsonPath('data.0.is_ready', true)
        ->assertJsonPath('data.0.is_pending', false)
        ->assertJsonPath('data.0.assigned_book_copy_id', $copy->id)
        ->assertJsonPath('data.0.book_copy_id', $copy->id)
        ->assertJsonPath('data.0.queue_position', null);

    $this->actingAs($admin)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('reservations.0.id', $reservation->id)
        ->assertJsonPath('reservations.0.status', Reservation::STATUS_READY)
        ->assertJsonPath('reservations.0.is_active', true)
        ->assertJsonPath('reservations.0.queue_position', null);

    $this->actingAs($admin)
        ->getJson('/api/auth/book-copies/'.$copy->id)
        ->assertOk()
        ->assertJsonPath('current_reservation.id', $reservation->id)
        ->assertJsonPath('current_reservation.status', Reservation::STATUS_READY)
        ->assertJsonPath('current_reservation.is_active', true)
        ->assertJsonPath('current_reservation.is_ready', true);
});

it('returns null pickup branch for reservations that are not ready', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $pickupBranch = Branch::factory()->create(['library_id' => $library->id]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
        'ready_at' => null,
        'expires_at' => null,
    ]);
    $reservation->forceFill(['pickup_branch_id' => $pickupBranch->id])->save();

    $this->actingAs($staff)
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $reservation->id)
        ->assertJsonPath('data.0.pickup_branch', null);
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
    ]);

    $this->actingAs($staff)
        ->getJson('/api/auth/books/'.$book->id)
        ->assertOk()
        ->assertJsonPath('reservations.0.id', $reservation->id)
        ->assertJsonPath('reservations.0.is_pending', true)
        ->assertJsonPath('reservations.0.can_cancel', true)
        ->assertJsonPath('reservations.0.display_status', 'Aktyvi')
        ->assertJsonPath('reservations.0.queue_position', 1)
        ->assertJsonPath('reservations.0.queue_size', 1);
});

it('orders api reservations by creation date descending', function () {
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
        ->assertJsonPath('data.0.id', $olderReservation->id)
        ->assertJsonPath('data.1.id', $newerReservation->id);
});

it('uses the serviceable branch reservation filter in api responses', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $memberA = User::factory()->member()->create(['library_id' => $library->id]);
    $memberB = User::factory()->member()->create(['library_id' => $library->id]);
    $memberLibrary = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $locationA = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchA->id]);
    $locationB = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchB->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchA->id,
        'location_id' => $locationA->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $branchAReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $memberA->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $branchBReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $memberB->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchB->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $libraryReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $memberLibrary->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/auth/reservations?branch_id='.$branchA->id)
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($branchAReservation->id, $libraryReservation->id)
        ->not->toContain($branchBReservation->id);
});
