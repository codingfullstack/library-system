<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Livewire\Reservations\CancelReservationForm;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sends an internal notification when staff cancels a reservation with a reason', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Pranešimų knyga']);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(CancelReservationAction::class)->handle($staff, $reservation, 'Nėradome tinkamo egzemplioriaus šiandien.');

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'reservation_cancelled',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reservation_cancelled',
        'auditable_type' => Reservation::class,
        'auditable_id' => $reservation->id,
    ]);
});

it('cancels a reservation through livewire without redirecting to the update endpoint', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Livewire rezervacija']);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    Livewire::actingAs($staff)
        ->test(CancelReservationForm::class, ['reservation' => $reservation])
        ->set('reason', 'Atšaukimo priežastis testui.')
        ->call('save')
        ->assertNoRedirect()
        ->assertDispatched('reservation-updated')
        ->assertDispatched('reservation-cancelled', reservationId: $reservation->id)
        ->assertSee('Atšaukta');

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_CANCELLED);
});

it('requires an override before issuing a copy when another member has an active reservation', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $reservedMember = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Override knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $reservedMember->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $otherMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(ValidationException::class);
});

it('allows reservation override with a required reason and records it in audit log', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $reservedMember = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Override leidziama']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-OVERRIDE-001',
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $reservedMember->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $result = app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $otherMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Skubu išduoti kitam nariui.',
        'override_reservation' => true,
        'override_reason' => 'Narys atvyko į vietą, rezervavęs narys dar neatvyko.',
    ]);

    expect($result['loan'])->not->toBeNull();
    expect($reservation->refresh()->status)->toBe(Reservation::STATUS_RESERVED)
        ->and($reservation->cancelled_at)->toBeNull()
        ->and($reservation->expires_at)->toBeNull()
        ->and($reservation->isPending())->toBeTrue()
        ->and($reservation->isCurrent())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reservation_override_issued',
        'auditable_type' => Reservation::class,
        'auditable_id' => $reservation->id,
    ]);

    $this->assertDatabaseMissing('notifications', [
        'notifiable_type' => $otherMember->getMorphClass(),
        'notifiable_id' => $otherMember->id,
        'type' => 'reservation_fulfilled',
    ]);

    $this->assertDatabaseMissing('notifications', [
        'notifiable_type' => $reservedMember->getMorphClass(),
        'notifiable_id' => $reservedMember->id,
        'type' => 'reservation_cancelled',
    ]);
});

it('does not keep a reservation ready when no copy is available after queue sync', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Laukiama knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(\App\Actions\Reservations\SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservation->refresh()->expires_at)->toBeNull()
        ->and($reservation->isCurrent())->toBeFalse()
        ->and($reservation->isPending())->toBeTrue();
});

it('shows notifications for the authenticated user', function () {
    $user = User::factory()->member()->create();
    $sender = User::factory()->staff()->create(['library_id' => $user->library_id]);

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'reservation_cancelled',
        'data' => [
            'kind' => 'reservation_cancelled',
            'title' => 'Rezervacija atsaukta',
            'message' => 'Tavo rezervacija buvo atšaukta.',
            'url' => route('notifications.index'),
            'created_at' => now()->toIso8601String(),
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
                'email' => $sender->email,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Pranešimai')
        ->assertSee('Rezervacija atsaukta')
        ->assertSee('Tavo rezervacija buvo atšaukta.');
});

it('creates an overdue notification when a member is at least one day late', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Vėluojanti knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $loan = \App\Models\Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => $member->id,
        'status' => 'vėluoja',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)
        ->get(route('books.index'))
        ->assertOk();

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'loan_overdue',
    ]);
});

it('does not duplicate the same overdue notification on later requests', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Nedubliuojama knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $loan = \App\Models\Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => $member->id,
        'status' => 'vėluoja',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)->get(route('books.index'))->assertOk();
    $this->actingAs($member)->get(route('books.index'))->assertOk();

    expect(
        $member->notifications()
            ->where('type', 'loan_overdue')
            ->where('data->related_type', \App\Models\Loan::class)
            ->where('data->related_id', $loan->id)
            ->count()
    )->toBe(1);
});

it('creates a reservation ready notification for the first waiting member', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Paruošta knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'status' => 'aktyvi',
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->subDay(),
        'returned_at' => null,
    ]);

    app(\App\Actions\Loans\ReturnBookCopyAction::class)->handle($staff, $bookCopy);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'reservation_ready',
    ]);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'book_returned',
    ]);
});

it('does not duplicate return side effects when the same copy is returned twice', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Single return book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'status' => 'aktyvi',
        'returned_at' => null,
    ]);

    app(\App\Actions\Loans\ReturnBookCopyAction::class)->handle($staff, $bookCopy);

    expect(fn () => app(\App\Actions\Loans\ReturnBookCopyAction::class)->handle($staff, $bookCopy->fresh()))
        ->toThrow(ValidationException::class);

    expect($loan->fresh()->returned_at)->not->toBeNull()
        ->and($bookCopy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);

    expect($member->notifications()
        ->where('type', 'book_returned')
        ->where('data->related_type', Loan::class)
        ->where('data->related_id', $loan->id)
        ->count())->toBe(1);

    expect(\App\Models\AuditLog::query()
        ->where('action', 'loan_returned')
        ->where('auditable_type', Loan::class)
        ->where('auditable_id', $loan->id)
        ->count())->toBe(1);

    expect(\App\Models\BookCopyStatusHistory::query()
        ->where('book_copy_id', $bookCopy->id)
        ->where('to_status', BookCopy::STATUS_AVAILABLE)
        ->count())->toBe(1);
});

it('creates a reservation fulfilled notification when reserved book is issued to the same member', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Įvykdyta rezervacija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
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
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Išduota pagal rezervacija.',
    ]);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'reservation_fulfilled',
    ]);
});

it('rolls back issued loan and reservation fulfillment if copy status update fails', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Rollback rezervacija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
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
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app()->bind(ChangeBookCopyStatusAction::class, fn () => new class extends ChangeBookCopyStatusAction {
        public function handle(
            BookCopy $bookCopy,
            string $toStatus,
            ?User $changedBy,
            string $reasonCode,
            ?string $reasonNotes = null,
            array $attributes = []
        ): BookCopy {
            throw new RuntimeException('Status update failed.');
        }
    });

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Turi atsisukti.',
    ]))->toThrow(RuntimeException::class);

    $this->assertDatabaseMissing('loans', [
        'book_copy_id' => $bookCopy->id,
        'user_id' => $member->id,
    ]);

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => Reservation::STATUS_RESERVED,
        'fulfilled_at' => null,
    ]);

    $this->assertDatabaseHas('book_copies', [
        'id' => $bookCopy->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
});

it('allows issuing an available copy to the member who is first in reservation queue', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Rezervuota kopija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_RESERVED,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $result = app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Išduota iš laisvos kopijos pagal rezervacijų eile.',
    ]);

    expect($result['loan'])->not->toBeNull();

    $this->assertDatabaseHas('book_copies', [
        'id' => $bookCopy->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
});

it('does not create a second active loan when the same copy is borrowed twice', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $firstMember = User::factory()->member()->create(['library_id' => $library->id]);
    $secondMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Single borrow book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $firstMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $bookCopy->fresh(), [
        'user_id' => $secondMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(ValidationException::class);

    expect(Loan::query()
        ->where('book_copy_id', $bookCopy->id)
        ->whereNull('returned_at')
        ->count())->toBe(1)
        ->and($bookCopy->fresh()->status)->toBe(BookCopy::STATUS_LOANED);
});





