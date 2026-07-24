<?php

use App\Models\Book;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authorizes staff cancellation of their branch reservation consistently for web and api', function (string $surface) {
    $fixture = branchCancellationFixture();

    $response = cancelReservationThrough($surface, $fixture['staff'], $fixture['ownReservation']);

    assertCancellationSucceeded($surface, $response);
    expect($fixture['ownReservation']->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
})->with(['web', 'api']);

it('rejects staff cancellation of another branch reservation consistently for web and api', function (string $surface) {
    $fixture = branchCancellationFixture();

    $response = cancelReservationThrough($surface, $fixture['staff'], $fixture['otherReservation']);

    assertCancellationDenied($surface, $response);
    expect($fixture['otherReservation']->fresh()->status)->toBe(Reservation::STATUS_WAITING);
})->with(['web', 'api']);

it('rejects branch reservation cancellation for staff without an assigned branch consistently for web and api', function (string $surface) {
    $fixture = branchCancellationFixture(assignStaffBranch: false);

    $response = cancelReservationThrough($surface, $fixture['staff'], $fixture['ownReservation']);

    assertCancellationDenied($surface, $response);
    expect($fixture['ownReservation']->fresh()->status)->toBe(Reservation::STATUS_WAITING);
})->with(['web', 'api']);

it('allows an admin to cancel branch reservations in their library consistently for web and api', function (string $surface) {
    $fixture = branchCancellationFixture();

    $response = cancelReservationThrough($surface, $fixture['admin'], $fixture['ownReservation']);

    assertCancellationSucceeded($surface, $response);
    expect($fixture['ownReservation']->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
})->with(['web', 'api']);

it('allows a super admin to cancel branch reservations consistently for web and api', function (string $surface) {
    $fixture = branchCancellationFixture();

    $response = cancelReservationThrough($surface, $fixture['superAdmin'], $fixture['otherReservation']);

    assertCancellationSucceeded($surface, $response);
    expect($fixture['otherReservation']->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
})->with(['web', 'api']);

function branchCancellationFixture(bool $assignStaffBranch = true): array
{
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id]);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $superAdmin = User::factory()->superAdmin()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id]);

    $staff->libraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $assignStaffBranch ? $ownBranch->id : null]);

    return [
        'staff' => $staff->fresh(),
        'admin' => $admin,
        'superAdmin' => $superAdmin,
        'ownReservation' => branchReservation($library->id, $book->id, $member->id, $ownBranch->id),
        'otherReservation' => branchReservation($library->id, $book->id, $otherMember->id, $otherBranch->id),
    ];
}

function branchReservation(int $libraryId, int $bookId, int $userId, int $branchId): Reservation
{
    return Reservation::factory()->create([
        'library_id' => $libraryId,
        'book_id' => $bookId,
        'user_id' => $userId,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchId,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
}

function cancelReservationThrough(string $surface, User $actor, Reservation $reservation)
{
    $payload = ['reason' => 'Autorizacijos testas.'];

    if ($surface === 'api') {
        return test()
            ->actingAs($actor)
            ->patchJson('/api/auth/reservations/'.$reservation->id.'/cancel', $payload);
    }

    return test()
        ->actingAs($actor)
        ->from(route('reservations.index'))
        ->patch(route('reservations.cancel', $reservation), $payload);
}

function assertCancellationSucceeded(string $surface, $response): void
{
    if ($surface === 'api') {
        $response->assertOk();

        return;
    }

    $response->assertRedirect(route('reservations.index'));
}

function assertCancellationDenied(string $surface, $response): void
{
    if ($surface === 'api') {
        $response->assertUnprocessable();

        return;
    }

    $response->assertSessionHasErrors('reservation');
}
