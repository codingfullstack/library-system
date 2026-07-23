<?php

use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function eligibleFifoFixture(): array
{
    $library = Library::factory()->create();
    $book = Book::factory()->create();
    $branches = collect(['A', 'B', 'C'])->mapWithKeys(function (string $name) use ($library) {
        $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => "Filialas {$name}"]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

        return [$name => ['branch' => $branch, 'location' => $location]];
    });

    return compact('library', 'book', 'branches');
}

function eligibleFifoMember(Library $library, string $name): User
{
    return User::factory()->member()->create([
        'library_id' => $library->id,
        'name' => $name,
    ]);
}

function eligibleFifoReservation(Library $library, Book $book, User $user, string $scope, ?Branch $branch, int $minutesAgo): Reservation
{
    return Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $user->id,
        'scope' => $scope,
        'branch_id' => $branch?->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes($minutesAgo),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
        'created_at' => now()->subMinutes($minutesAgo),
        'updated_at' => now()->subMinutes($minutesAgo),
    ]);
}

function eligibleFifoAvailableCopy(array $fixture, string $branchName): BookCopy
{
    return BookCopy::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'branch_id' => $fixture['branches'][$branchName]['branch']->id,
        'location_id' => $fixture['branches'][$branchName]['location']->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
}

it('selects the older library reservation over a later branch reservation when a branch B copy is free', function () {
    $fixture = eligibleFifoFixture();
    $jonas = eligibleFifoMember($fixture['library'], 'Jonas');
    $petras = eligibleFifoMember($fixture['library'], 'Petras');
    $ona = eligibleFifoMember($fixture['library'], 'Ona');

    eligibleFifoReservation($fixture['library'], $fixture['book'], $jonas, Reservation::SCOPE_BRANCH, $fixture['branches']['A']['branch'], 30);
    $petrasReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $petras, Reservation::SCOPE_LIBRARY, null, 20);
    eligibleFifoReservation($fixture['library'], $fixture['book'], $ona, Reservation::SCOPE_BRANCH, $fixture['branches']['B']['branch'], 10);
    $copy = eligibleFifoAvailableCopy($fixture, 'B');

    expect(app(ReservationQueueService::class)->getEligibleReservationForCopy($copy)?->id)->toBe($petrasReservation->id);

    app(SyncReservationQueueAction::class)->handle($fixture['library']->id, $fixture['book']->id);

    expect($petrasReservation->fresh()->status)->toBe(Reservation::STATUS_READY);
});

it('does not prioritize a branch B reservation over an older library reservation', function () {
    $fixture = eligibleFifoFixture();
    $jonas = eligibleFifoMember($fixture['library'], 'Jonas');
    $petras = eligibleFifoMember($fixture['library'], 'Petras');
    $ona = eligibleFifoMember($fixture['library'], 'Ona');

    $jonasReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $jonas, Reservation::SCOPE_LIBRARY, null, 30);
    eligibleFifoReservation($fixture['library'], $fixture['book'], $petras, Reservation::SCOPE_BRANCH, $fixture['branches']['B']['branch'], 20);
    eligibleFifoReservation($fixture['library'], $fixture['book'], $ona, Reservation::SCOPE_LIBRARY, null, 10);
    $copy = eligibleFifoAvailableCopy($fixture, 'B');

    expect(app(ReservationQueueService::class)->getEligibleReservationForCopy($copy)?->id)->toBe($jonasReservation->id);
});

it('selects the older branch B reservation before a later library reservation', function () {
    $fixture = eligibleFifoFixture();
    $jonas = eligibleFifoMember($fixture['library'], 'Jonas');
    $petras = eligibleFifoMember($fixture['library'], 'Petras');

    $jonasReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $jonas, Reservation::SCOPE_BRANCH, $fixture['branches']['B']['branch'], 20);
    eligibleFifoReservation($fixture['library'], $fixture['book'], $petras, Reservation::SCOPE_LIBRARY, null, 10);
    $copy = eligibleFifoAvailableCopy($fixture, 'B');

    expect(app(ReservationQueueService::class)->getEligibleReservationForCopy($copy)?->id)->toBe($jonasReservation->id);
});

it('does not prepare anyone when a branch C copy cannot serve branch A or branch B reservations', function () {
    $fixture = eligibleFifoFixture();
    $jonas = eligibleFifoMember($fixture['library'], 'Jonas');
    $petras = eligibleFifoMember($fixture['library'], 'Petras');

    $jonasReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $jonas, Reservation::SCOPE_BRANCH, $fixture['branches']['A']['branch'], 20);
    $petrasReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $petras, Reservation::SCOPE_BRANCH, $fixture['branches']['B']['branch'], 10);
    $copy = eligibleFifoAvailableCopy($fixture, 'C');

    expect(app(ReservationQueueService::class)->getEligibleReservationForCopy($copy))->toBeNull();

    app(SyncReservationQueueAction::class)->handle($fixture['library']->id, $fixture['book']->id);

    expect($jonasReservation->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($petrasReservation->fresh()->status)->toBe(Reservation::STATUS_WAITING);
});

it('expires only elapsed ready reservations and then syncs that book queue', function () {
    $fixture = eligibleFifoFixture();
    $jonas = eligibleFifoMember($fixture['library'], 'Jonas');
    $petras = eligibleFifoMember($fixture['library'], 'Petras');
    $copy = eligibleFifoAvailableCopy($fixture, 'A');

    $expiredReadyReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $jonas, Reservation::SCOPE_LIBRARY, null, 30);
    $expiredReadyReservation->update([
        'status' => Reservation::STATUS_READY,
        'pickup_branch_id' => $copy->branch_id,
        'assigned_book_copy_id' => $copy->id,
        'ready_at' => now()->subDays(2),
        'expires_at' => now()->subMinute(),
    ]);

    $nextReservation = eligibleFifoReservation($fixture['library'], $fixture['book'], $petras, Reservation::SCOPE_LIBRARY, null, 20);

    $this->artisan('reservations:expire')->assertSuccessful();

    expect($expiredReadyReservation->fresh()->status)->toBe(Reservation::STATUS_EXPIRED)
        ->and($nextReservation->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and(app(ReservationQueueService::class)->getEligibleReservationForCopy($copy)?->id)->toBe($nextReservation->id);
});
