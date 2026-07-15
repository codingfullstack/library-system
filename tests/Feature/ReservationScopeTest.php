<?php

use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Livewire\Reservations\CreateReservationForm;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Services\ReservationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reservationScopeFixture(): array
{
    $library = Library::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Pirmas filialas']);
    $branchB = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Antras filialas']);
    $locationA = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchA->id]);
    $locationB = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchB->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copyA = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchA->id,
        'location_id' => $locationA->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    $copyB = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $staff->activeLibraryMemberships()
        ->where('library_id', $library->id)
        ->update(['branch_id' => $branchA->id]);

    return compact('library', 'branchA', 'branchB', 'staff', 'admin', 'member', 'book', 'copyA', 'copyB');
}

it('allows staff to create a branch scoped reservation in own branch', function () {
    $fixture = reservationScopeFixture();

    $reservation = app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
    ]);

    expect($reservation->scope)->toBe(Reservation::SCOPE_BRANCH)
        ->and((int) $reservation->branch_id)->toBe((int) $fixture['branchA']->id);
});

it('prevents staff from creating a branch scoped reservation in another branch', function () {
    $fixture = reservationScopeFixture();

    expect(fn () => app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
    ]))->toThrow(ValidationException::class);
});

it('allows staff to create a library scoped reservation in own library', function () {
    $fixture = reservationScopeFixture();

    $reservation = app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_LIBRARY,
    ]);

    expect($reservation->scope)->toBe(Reservation::SCOPE_LIBRARY)
        ->and($reservation->branch_id)->toBeNull();
});

it('shows library scoped reservations in branch filters by serviceable branch instead of creator branch', function () {
    $fixture = reservationScopeFixture();
    $branchC = Branch::factory()->create(['library_id' => $fixture['library']->id, 'name' => 'Trecias filialas']);
    $branchWithoutCopies = Branch::factory()->create(['library_id' => $fixture['library']->id, 'name' => 'Be kopiju']);
    $locationC = Location::factory()->create(['library_id' => $fixture['library']->id, 'branch_id' => $branchC->id]);
    $memberB = User::factory()->member()->create(['library_id' => $fixture['library']->id]);
    $memberLibrary = User::factory()->member()->create(['library_id' => $fixture['library']->id]);

    BookCopy::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'branch_id' => $branchC->id,
        'location_id' => $locationC->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $branchAReservation = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $branchBReservation = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $memberB->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $libraryReservation = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $memberLibrary->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $query = app(GetLibraryReservationsQuery::class);

    $allIds = collect($query->handle($fixture['admin'], ['per_page' => 10])->items())->pluck('id');
    $branchAIds = collect($query->handle($fixture['admin'], ['branch_id' => $fixture['branchA']->id, 'per_page' => 10])->items())->pluck('id');
    $branchBIds = collect($query->handle($fixture['admin'], ['branch_id' => $fixture['branchB']->id, 'per_page' => 10])->items())->pluck('id');
    $branchCIds = collect($query->handle($fixture['admin'], ['branch_id' => $branchC->id, 'per_page' => 10])->items())->pluck('id');
    $emptyBranchIds = collect($query->handle($fixture['admin'], ['branch_id' => $branchWithoutCopies->id, 'per_page' => 10])->items())->pluck('id');

    expect($allIds->all())->toContain($branchAReservation->id, $branchBReservation->id, $libraryReservation->id)
        ->and($branchAIds->all())->toContain($branchAReservation->id, $libraryReservation->id)
        ->and($branchAIds->all())->not->toContain($branchBReservation->id)
        ->and($branchBIds->all())->toContain($branchBReservation->id, $libraryReservation->id)
        ->and($branchBIds->all())->not->toContain($branchAReservation->id)
        ->and($branchCIds->all())->toContain($libraryReservation->id)
        ->and($branchCIds->all())->not->toContain($branchAReservation->id, $branchBReservation->id)
        ->and($emptyBranchIds->all())->not->toContain($libraryReservation->id, $branchAReservation->id, $branchBReservation->id);
});

it('prevents staff from creating reservations in another library', function () {
    $fixture = reservationScopeFixture();
    $otherLibrary = Library::factory()->create();
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);
    $otherLocation = Location::factory()->create(['library_id' => $otherLibrary->id, 'branch_id' => $otherBranch->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $otherLibrary->id]);
    $otherBook = Book::factory()->create();

    BookCopy::factory()->create([
        'library_id' => $otherLibrary->id,
        'book_id' => $otherBook->id,
        'branch_id' => $otherBranch->id,
        'location_id' => $otherLocation->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    expect(fn () => app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $otherBook->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_LIBRARY,
    ]))->toThrow(ValidationException::class);
});

it('checks available copies only in the selected branch for branch scope', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyB']->update(['status' => BookCopy::STATUS_AVAILABLE]);

    $reservation = app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
    ]);

    expect($reservation->scope)->toBe(Reservation::SCOPE_BRANCH);
});

it('checks available copies across all branches for library scope', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyB']->update(['status' => BookCopy::STATUS_AVAILABLE]);

    expect(fn () => app(CreateReservationAction::class)->handle($fixture['staff'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_LIBRARY,
    ]))->toThrow(ValidationException::class);
});

it('calculates branch scoped queue position in the shared library book queue', function () {
    $fixture = reservationScopeFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $fixture['library']->id]);
    $thirdMember = User::factory()->member()->create(['library_id' => $fixture['library']->id]);

    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $firstOwnBranch = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $secondOwnBranch = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $thirdMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $queue = app(ReservationQueueService::class);

    expect($queue->positionFor($firstOwnBranch))->toBe(2)
        ->and($queue->positionFor($secondOwnBranch))->toBe(3);
});

it('calculates library scoped queue position in the shared library book queue', function () {
    $fixture = reservationScopeFixture();
    $otherMember = User::factory()->member()->create(['library_id' => $fixture['library']->id]);
    $thirdMember = User::factory()->member()->create(['library_id' => $fixture['library']->id]);

    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $firstLibrary = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $secondLibrary = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $thirdMember->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $queue = app(ReservationQueueService::class);

    expect($queue->positionFor($firstLibrary))->toBe(2)
        ->and($queue->positionFor($secondLibrary))->toBe(3);
});

it('keeps one shared queue for mixed branch and library reservations', function () {
    $fixture = reservationScopeFixture();
    $members = User::factory()->count(5)->member()->create(['library_id' => $fixture['library']->id]);
    $queue = app(ReservationQueueService::class);

    $reservations = collect([
        [Reservation::SCOPE_LIBRARY, null],
        [Reservation::SCOPE_BRANCH, $fixture['branchA']->id],
        [Reservation::SCOPE_BRANCH, $fixture['branchB']->id],
        [Reservation::SCOPE_LIBRARY, null],
        [Reservation::SCOPE_BRANCH, $fixture['branchA']->id],
    ])->map(function (array $scope, int $index) use ($fixture, $members) {
        return Reservation::factory()->create([
            'library_id' => $fixture['library']->id,
            'book_id' => $fixture['book']->id,
            'user_id' => $members[$index]->id,
            'scope' => $scope[0],
            'branch_id' => $scope[1],
            'status' => Reservation::STATUS_RESERVED,
            'reserved_at' => now()->subMinutes(10 - $index),
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);
    });

    expect($reservations->map(fn (Reservation $reservation) => $queue->positionFor($reservation))->all())
        ->toBe([1, 2, 3, 4, 5]);
});

it('does not include expired cancelled or fulfilled reservations in queue positions', function () {
    $fixture = reservationScopeFixture();
    $members = User::factory()->count(4)->member()->create(['library_id' => $fixture['library']->id]);
    $queue = app(ReservationQueueService::class);

    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_CANCELLED,
        'reserved_at' => now()->subMinutes(4),
        'cancelled_at' => now()->subMinute(),
    ]);
    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_FULFILLED,
        'reserved_at' => now()->subMinutes(3),
        'fulfilled_at' => now()->subMinute(),
    ]);
    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[2]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => now()->subMinute(),
    ]);
    $activeReservation = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[3]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect($queue->positionFor($activeReservation))->toBe(1);
});

it('moves queue positions forward after the first reservation is cancelled', function () {
    $fixture = reservationScopeFixture();
    $members = User::factory()->count(3)->member()->create(['library_id' => $fixture['library']->id]);
    $queue = app(ReservationQueueService::class);

    $first = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinutes(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $third = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[2]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect($queue->positionFor($first))->toBe(1)
        ->and($queue->positionFor($second))->toBe(2)
        ->and($queue->positionFor($third))->toBe(3);

    $first->update([
        'status' => Reservation::STATUS_CANCELLED,
        'cancelled_at' => now(),
    ]);

    expect($queue->positionFor($second->fresh()))->toBe(1)
        ->and($queue->positionFor($third->fresh()))->toBe(2);
});

it('does not prepare a later serviceable reservation when an earlier reservation blocks the shared queue', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyB']->update(['status' => BookCopy::STATUS_AVAILABLE]);
    $members = User::factory()->count(3)->member()->create(['library_id' => $fixture['library']->id]);

    $branchAOnly = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinutes(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $libraryWide = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $branchBOnly = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $members[2]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($fixture['library']->id, $fixture['book']->id);

    expect($branchAOnly->fresh()->expires_at)->toBeNull()
        ->and($libraryWide->fresh()->expires_at)->toBeNull()
        ->and($branchBOnly->fresh()->expires_at)->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($libraryWide->fresh()))->toBe(2);

    expect($members[1]->notifications()->where('type', 'reservation_ready')->count())->toBe(0)
        ->and($members[2]->notifications()->where('type', 'reservation_ready')->count())->toBe(0);
});

it('sends ready notifications according to reservation scope', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyB']->update(['status' => BookCopy::STATUS_AVAILABLE]);

    $reservation = Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($fixture['library']->id, $fixture['book']->id);

    $this->assertDatabaseMissing('notifications', [
        'notifiable_id' => $fixture['member']->id,
        'type' => 'reservation_ready',
    ]);
    expect($reservation->fresh()->expires_at)->toBeNull();

    $fixture['copyA']->update(['status' => BookCopy::STATUS_AVAILABLE]);

    app(SyncReservationQueueAction::class)->handle($fixture['library']->id, $fixture['book']->id);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $fixture['member']->id,
        'type' => 'reservation_ready',
    ]);
    expect($reservation->fresh()->expires_at)->not->toBeNull();
});

it('allows admins to create branch scoped reservations in any branch of their library', function () {
    $fixture = reservationScopeFixture();

    $reservation = app(CreateReservationAction::class)->handle($fixture['admin'], [
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchB']->id,
    ]);

    expect($reservation->scope)->toBe(Reservation::SCOPE_BRANCH)
        ->and((int) $reservation->branch_id)->toBe((int) $fixture['branchB']->id);
});

it('lets staff switch to library scope when the book has no copies in their branch', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyA']->delete();

    $this->actingAs($fixture['staff'])
        ->get(route('books.show', ['book' => $fixture['book'], 'tab' => 'reservations']))
        ->assertOk()
        ->assertSee('Šios knygos kopijų pasirinktame filiale nėra. Galite rinktis rezervaciją visoje bibliotekoje.')
        ->assertSee('Visoje bibliotekoje')
        ->assertSee('Skaitytojas gali būti pakviestas pasiimti knygą iš bet kurio šios bibliotekos filialo.')
        ->assertSee('name="reservation_scope"', false);

    Livewire::actingAs($fixture['staff'])
        ->test(CreateReservationForm::class, ['book' => $fixture['book']])
        ->assertSee('Šios knygos kopijų pasirinktame filiale nėra.')
        ->assertDontSee('Sukurti rezervaciją')
        ->set('scope', Reservation::SCOPE_LIBRARY)
        ->assertSee('Sukurti rezervaciją')
        ->set('memberSearch', $fixture['member']->email)
        ->assertSee($fixture['member']->name);
});

it('shows member search results even when the current reservation scope is blocked', function () {
    $fixture = reservationScopeFixture();
    $fixture['copyA']->delete();

    Livewire::actingAs($fixture['staff'])
        ->test(CreateReservationForm::class, ['book' => $fixture['book']])
        ->assertSee('Šios knygos kopijų pasirinktame filiale nėra.')
        ->set('memberSearch', $fixture['member']->name)
        ->assertSee($fixture['member']->name)
        ->assertDontSee('Narių pagal šią paiešką nerasta.');
});

it('shows copy reservation details on the web book details page', function () {
    $fixture = reservationScopeFixture();

    Reservation::factory()->create([
        'library_id' => $fixture['library']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $fixture['member']->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA']->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($fixture['staff'])
        ->get(route('books.show', ['book' => $fixture['book'], 'tab' => 'copies']))
        ->assertOk()
        ->assertSee('Paruošta atsiėmimui')
        ->assertSee('Rezervacijos informacija')
        ->assertSee('Rezervavo')
        ->assertSee($fixture['member']->name)
        ->assertSee($fixture['member']->membership_number)
        ->assertSee('Galioja iki')
        ->assertSee('Aktyvus išdavimas')
        ->assertSee('Rezervacijos pozicija')
        ->assertSee('Rezervacijos statusas')
        ->assertSee('Rezervacijos sukūrimo data');
});
