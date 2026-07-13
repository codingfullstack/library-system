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

it('calculates branch scoped queue position only within that branch', function () {
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

    expect($queue->positionFor($firstOwnBranch))->toBe(1)
        ->and($queue->positionFor($secondOwnBranch))->toBe(2);
});

it('calculates library scoped queue position across the library queue only', function () {
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

    expect($queue->positionFor($firstLibrary))->toBe(1)
        ->and($queue->positionFor($secondLibrary))->toBe(2);
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
