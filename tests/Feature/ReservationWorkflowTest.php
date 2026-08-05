<?php

use App\Actions\BookCopies\ChangeBookCopyStatusAction;
use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Livewire\Reservations\CancelReservationForm;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCopyStatusHistory;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Queries\Books\GetLibraryBookDetailsQuery;
use App\Queries\Reservations\GetLibraryReservationsQuery;
use App\Services\ReservationNotificationService;
use App\Services\ReservationQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(CancelReservationAction::class)->handle($staff, $reservation, 'Nėradome tinkamo kopijos šiandien.');

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'reservation_cancelled',
    ]);

    $notification = $member->notifications()->where('type', 'reservation_cancelled')->first();

    expect($notification->data['message'])->not->toContain('Atsiėmimo filialas')
        ->and($notification->data['metadata'])->not->toHaveKeys(['pickup_branch_id', 'pickup_branch_name']);

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
        'status' => Reservation::STATUS_WAITING,
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

it('rejects issuing a copy to another member when fifo has an active reservation', function () {
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'ready_at' => null,
        'expires_at' => null,
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
it('rejects reservation override fields and keeps fifo reservation untouched', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $reservedMember = User::factory()->member()->create(['library_id' => $library->id]);
    $otherMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'FIFO enforced']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-FIFO-001',
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $reservedMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $bookCopy, [
        'user_id' => $otherMember->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Client attempted stale override fields.',
        'override_reservation' => true,
        'override_reason' => 'Ignored stale client field.',
    ]))->toThrow(ValidationException::class);

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($bookCopy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE)
        ->and(Loan::query()->where('book_copy_id', $bookCopy->id)->count())->toBe(0);

    $this->assertDatabaseMissing('audit_logs', [
        'action' => 'reservation_override_issued',
    ]);
});
it('promotes waiting reservations to ready without cancelling expiring or fulfilling them', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'READY lifecycle']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $loanedCopy = BookCopy::factory()->create([
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
        'pickup_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    $reservation->refresh();

    expect($reservation->status)->toBe(Reservation::STATUS_READY)
        ->and($reservation->isActive())->toBeTrue()
        ->and($reservation->isPending())->toBeFalse()
        ->and($reservation->cancelled_at)->toBeNull()
        ->and($reservation->fulfilled_at)->toBeNull()
        ->and($reservation->pickup_branch_id)->toBe($branch->id)
        ->and($reservation->assigned_book_copy_id)->not->toBeNull();

    expect(Reservation::query()->active()->pluck('id')->all())->toContain($reservation->id)
        ->and(Reservation::query()->expired()->pluck('id')->all())->not->toContain($reservation->id);
});

it('keeps fifo order when two available copies promote the first two waiting reservations', function () {
    $library = Library::factory()->create();
    $book = Book::factory()->create(['title' => 'FIFO two copies']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);

    BookCopy::factory()->count(2)->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = collect(range(0, 2))->map(fn (int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[$index]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(30 - $index),
        'created_at' => now()->subMinutes(30 - $index),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[1]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[2]->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservations[0]->fresh()->assigned_book_copy_id)->not->toBe($reservations[1]->fresh()->assigned_book_copy_id)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->queueSize($library->id, $book->id))->toBe(1);
});

it('keeps a ready reservation active when no copy is available after queue sync', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Laukiama knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    $loanedCopy = BookCopy::factory()->create([
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
        'status' => Reservation::STATUS_READY,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $loanedCopy->id,
        'reserved_at' => now()->subHour(),
        'ready_at' => now()->subMinutes(30),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservation->refresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservation->ready_at)->not->toBeNull()
        ->and($reservation->expires_at)->not->toBeNull()
        ->and($reservation->isReady())->toBeTrue()
        ->and(app(ReservationQueueService::class)->positionFor($reservation))->toBeNull();
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

    $loan = Loan::factory()->create([
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

    $loan = Loan::factory()->create([
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
            ->where('data->related_type', Loan::class)
            ->where('data->related_id', $loan->id)
            ->count()
    )->toBe(1);
});

it('creates a reservation ready notification for the first waiting member', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Paruošta knyga']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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
        'status' => Reservation::STATUS_WAITING,
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

    app(ReturnBookCopyAction::class)->handle($staff, $bookCopy);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'reservation_ready',
    ]);

    $readyNotification = $member->notifications()->where('type', 'reservation_ready')->first();

    expect($readyNotification)->not->toBeNull()
        ->and($readyNotification->data['title'])->toBe('Rezervacija paruošta')
        ->and($readyNotification->data['message'])->toContain('jau laukia jūsų')
        ->and($readyNotification->data['message'])->toContain($branch->name)
        ->and($readyNotification->data['metadata']['pickup_branch_id'])->toBe($branch->id)
        ->and($readyNotification->data['metadata']['pickup_branch_name'])->toBe($branch->name);

    $this->assertDatabaseHas('notifications', [
        'notifiable_type' => $member->getMorphClass(),
        'notifiable_id' => $member->id,
        'type' => 'book_returned',
    ]);
});

it('does not duplicate return side effects when the same copy is returned twice', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Single return book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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

    app(ReturnBookCopyAction::class)->handle($staff, $bookCopy);

    expect(fn () => app(ReturnBookCopyAction::class)->handle($staff, $bookCopy->fresh()))
        ->toThrow(ValidationException::class);

    expect($loan->fresh()->returned_at)->not->toBeNull()
        ->and($bookCopy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);

    expect($member->notifications()
        ->where('type', 'book_returned')
        ->where('data->related_type', Loan::class)
        ->where('data->related_id', $loan->id)
        ->count())->toBe(1);

    $returnNotification = $member->notifications()->where('type', 'book_returned')->first();

    expect($returnNotification->data['message'])->toContain($branch->name)
        ->and($returnNotification->data['metadata']['branch_id'])->toBe($branch->id)
        ->and($returnNotification->data['metadata']['branch_name'])->toBe($branch->name);

    expect(AuditLog::query()
        ->where('action', 'loan_returned')
        ->where('auditable_type', Loan::class)
        ->where('auditable_id', $loan->id)
        ->count())->toBe(1);

    expect(BookCopyStatusHistory::query()
        ->where('book_copy_id', $bookCopy->id)
        ->where('to_status', BookCopy::STATUS_AVAILABLE)
        ->count())->toBe(1);
});

it('creates a reservation fulfilled notification when reserved book is issued to the same member', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Įvykdyta rezervacija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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
        'status' => Reservation::STATUS_READY,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $bookCopy->id,
        'reserved_at' => now()->subHour(),
        'ready_at' => now()->subMinutes(30),
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

    $fulfilledNotification = $member->notifications()->where('type', 'reservation_fulfilled')->first();

    expect($fulfilledNotification->data['message'])->toContain($branch->name)
        ->and($fulfilledNotification->data['metadata']['branch_id'])->toBe($branch->id)
        ->and($fulfilledNotification->data['metadata']['branch_name'])->toBe($branch->name);
});

it('rolls back issued loan and reservation fulfillment if copy status update fails', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Rollback rezervacija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subHour(),
        'ready_at' => now()->subMinutes(30),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app()->bind(ChangeBookCopyStatusAction::class, fn () => new class extends ChangeBookCopyStatusAction
    {
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
        'status' => Reservation::STATUS_READY,
        'fulfilled_at' => null,
    ]);

    $this->assertDatabaseHas('book_copies', [
        'id' => $bookCopy->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
});

it('allows issuing an available copy to the member who is first in reservation queue', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Rezervuota kopija']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subHour(),
        'ready_at' => now()->subMinutes(30),
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

it('notifies the member with queue position and due date after a reservation is created', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Queue info book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => User::factory()->member()->create(['library_id' => $library->id])->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'due_at' => '2026-06-20',
        'returned_at' => null,
    ]);

    app(CreateReservationAction::class)->handle($member, [
        'book_id' => $book->id,
    ]);

    $notification = $member->notifications()->where('type', 'reservation_created')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Rezervacija sukurta')
        ->and($notification->data['metadata']['queue_position'])->toBe(1)
        ->and($notification->data['metadata']['due_at'])->toBe('2026-06-20')
        ->and($notification->data['message'])->toContain('Jūs sėkmingai rezervavote knygą')
        ->and($notification->data['message'])->toContain('Jūsų vieta eilėje: 1')
        ->and($notification->data['message'])->toContain('Šiuo metu knyga paskolinta kitam skaitytojui iki 2026-06-20.');
});

it('notifies the next waiting member when the first reservation is cancelled', function () {
    $library = Library::factory()->create();
    $firstMember = User::factory()->member()->create(['library_id' => $library->id]);
    $secondMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Moving queue book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $bookCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $bookCopy->id,
        'user_id' => User::factory()->member()->create(['library_id' => $library->id])->id,
        'status' => Loan::STATUS_ACTIVE,
        'due_at' => '2026-06-20',
        'returned_at' => null,
    ]);

    $firstReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $firstMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $secondReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $secondMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($firstReservation);
    app(ReservationNotificationService::class)->notifyCreated($secondReservation);

    app(CancelReservationAction::class)->handle($firstMember, $firstReservation);

    $notification = $secondMember->notifications()->where('type', 'reservation_queue_changed')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toBe('Rezervacijos eilė pasikeitė')
        ->and($notification->data['metadata']['old_queue_position'])->toBe(2)
        ->and($notification->data['metadata']['new_queue_position'])->toBe(1)
        ->and($notification->data['metadata']['due_at'])->toBe('2026-06-20')
        ->and($notification->data['message'])->toContain('Jūsų rezervacijos eilė pasikeitė')
        ->and($notification->data['message'])->toContain('Dabartinis skaitytojas turi grąžinti knygą iki 2026-06-20.');
});

it('does not create a queue changed notification when the position did not change', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Stable queue book']);
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($reservation);
    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($member->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(0);
});

it('does not move reservation queue notifications across library boundaries', function () {
    $firstLibrary = Library::factory()->create();
    $secondLibrary = Library::factory()->create();
    $firstMember = User::factory()->member()->create(['library_id' => $firstLibrary->id]);
    $secondMember = User::factory()->member()->create(['library_id' => $firstLibrary->id]);
    $otherLibraryMember = User::factory()->member()->create(['library_id' => $secondLibrary->id]);
    $book = Book::factory()->create(['title' => 'Shared catalog book']);
    $firstBranch = Branch::factory()->create(['library_id' => $firstLibrary->id]);
    $firstLocation = Location::factory()->create(['library_id' => $firstLibrary->id, 'branch_id' => $firstBranch->id]);
    $secondBranch = Branch::factory()->create(['library_id' => $secondLibrary->id]);
    $secondLocation = Location::factory()->create(['library_id' => $secondLibrary->id, 'branch_id' => $secondBranch->id]);

    BookCopy::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $firstBranch->id,
        'location_id' => $firstLocation->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    BookCopy::factory()->create([
        'library_id' => $secondLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $secondBranch->id,
        'location_id' => $secondLocation->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $firstReservation = Reservation::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $book->id,
        'user_id' => $firstMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $secondReservation = Reservation::factory()->create([
        'library_id' => $firstLibrary->id,
        'book_id' => $book->id,
        'user_id' => $secondMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $otherLibraryReservation = Reservation::factory()->create([
        'library_id' => $secondLibrary->id,
        'book_id' => $book->id,
        'user_id' => $otherLibraryMember->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(30),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($firstReservation);
    app(ReservationNotificationService::class)->notifyCreated($secondReservation);
    app(ReservationNotificationService::class)->notifyCreated($otherLibraryReservation);

    app(CancelReservationAction::class)->handle($firstMember, $firstReservation);

    expect($secondMember->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and($otherLibraryMember->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(0);
});

it('does not create a second active loan when the same copy is borrowed twice', function () {
    $library = Library::factory()->create();
    $firstMember = User::factory()->member()->create(['library_id' => $library->id]);
    $secondMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Single borrow book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
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

it('uses the same global queue position in query api and queue change notifications', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(4)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Consistent queue book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    $reservations = $members->values()->map(function (User $member, int $index) use ($library, $book, $branch) {
        return Reservation::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'scope' => $index === 1 ? Reservation::SCOPE_BRANCH : Reservation::SCOPE_LIBRARY,
            'branch_id' => $index === 1 ? $branch->id : null,
            'status' => Reservation::STATUS_WAITING,
            'reserved_at' => now()->subMinutes(4 - $index),
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);
    });

    $targetReservation = $reservations[3];

    app(ReservationNotificationService::class)->notifyCreated($targetReservation);

    app(CancelReservationAction::class)->handle($staff, $reservations[1], 'Queue consistency test.');

    $expectedPosition = app(ReservationQueueService::class)->positionFor($targetReservation->fresh());
    $queryReservation = collect(app(GetLibraryReservationsQuery::class)->handle($staff, ['per_page' => 10])->items())
        ->firstWhere('id', $targetReservation->id);

    $apiReservation = collect($this->actingAs($staff)
        ->getJson('/api/auth/reservations?per_page=10')
        ->assertOk()
        ->json('data'))
        ->firstWhere('id', $targetReservation->id);

    $queueChanged = $members[3]->notifications()
        ->where('type', 'reservation_queue_changed')
        ->where('data->related_id', $targetReservation->id)
        ->first();

    expect($expectedPosition)->toBe(3)
        ->and((int) $queryReservation->queue_position)->toBe($expectedPosition)
        ->and($apiReservation['queue_position'])->toBe($expectedPosition)
        ->and($queueChanged)->not->toBeNull()
        ->and($queueChanged->data['metadata']['new_queue_position'])->toBe($expectedPosition);
});

it('prepares a later serviceable reservation when an earlier reservation cannot be served by the free copy', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Ready without queue noise']);
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
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(3),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $libraryReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[2]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchB->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($libraryReservation);
    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($libraryReservation->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($libraryReservation->fresh()->ready_at)->not->toBeNull()
        ->and($libraryReservation->fresh()->expires_at)->not->toBeNull()
        ->and($members[1]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and($members[1]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(0);
});

it('exposes queue position on the reservation selected for a copy', function () {
    $library = Library::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(2),
    ]);

    $serviceableReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
    ]);

    $selected = app(ReservationQueueService::class)->getEligibleReservationForCopy($copy);

    expect($selected?->id)->toBe($serviceableReservation->id)
        ->and($selected->queue_position)->toBe(2)
        ->and($selected->queue_size)->toBe(2);
});

it('sets pickup branch from the serving copy when a reservation becomes ready', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->create([
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
        'pickup_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    $reservation->refresh();

    expect($reservation->status)->toBe(Reservation::STATUS_READY)
        ->and($reservation->branch_id)->toBeNull()
        ->and($reservation->pickup_branch_id)->toBe($branch->id);
});

it('clears pickup branch when a ready reservation is cancelled or expired', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);

    $cancelledReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branch->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subDay(),
        'ready_at' => now()->subHour(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(CancelReservationAction::class)->handle($admin, $cancelledReservation, 'Nebereikalinga');

    expect($cancelledReservation->fresh()->pickup_branch_id)->toBeNull();

    $cancelledNotification = $member->notifications()
        ->where('type', 'reservation_cancelled')
        ->where('data->related_id', $cancelledReservation->id)
        ->first();

    expect($cancelledNotification)->not->toBeNull()
        ->and($cancelledNotification->data['message'])->toContain($branch->name)
        ->and($cancelledNotification->data['metadata']['pickup_branch_id'])->toBe($branch->id)
        ->and($cancelledNotification->data['metadata']['pickup_branch_name'])->toBe($branch->name);

    $expiredReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branch->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subDays(3),
        'ready_at' => now()->subDays(2),
        'expires_at' => now()->subMinute(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->artisan('reservations:expire')->assertSuccessful();

    expect($expiredReservation->fresh()->status)->toBe(Reservation::STATUS_EXPIRED)
        ->and($expiredReservation->fresh()->pickup_branch_id)->toBeNull();

    $expiredNotification = $member->notifications()
        ->where('type', 'reservation_expired')
        ->where('data->related_id', $expiredReservation->id)
        ->first();

    expect($expiredNotification)->not->toBeNull()
        ->and($expiredNotification->data['message'])->toContain($branch->name)
        ->and($expiredNotification->data['metadata']['pickup_branch_id'])->toBe($branch->id)
        ->and($expiredNotification->data['metadata']['pickup_branch_name'])->toBe($branch->name);
});

it('uses only active unreturned loans when adding due dates to reservation notifications', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $loanMember = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Loan due source book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $oldCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    $activeCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $oldCopy->id,
        'user_id' => $loanMember->id,
        'status' => Loan::STATUS_RETURNED,
        'returned_at' => null,
        'due_at' => '2026-06-20',
    ]);
    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $activeCopy->id,
        'user_id' => $loanMember->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'due_at' => '2026-08-01',
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($reservation);

    $notification = $member->notifications()->where('type', 'reservation_created')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['metadata']['due_at'])->toBe('2026-08-01')
        ->and($notification->data['message'])->not->toContain('2026-06-20')
        ->and($notification->data['message'])->toContain('2026-08-01');
});

it('uses id as a stable tiebreaker when reservation timestamps match', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $timestamp = now()->subHour();

    $first = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $timestamp,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $timestamp,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect(app(ReservationQueueService::class)->positionFor($first))->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($second))->toBe(2);
});

it('does not move other reservations when a returned copy only makes the first reservation ready', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $loanMember = User::factory()->member()->create(['library_id' => $library->id]);
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Return keeps queue book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $loanMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(3 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[1]);
    app(ReservationNotificationService::class)->notifyCreated($reservations[2]);

    app(ReturnBookCopyAction::class)->handle($staff, $copy);

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[0]->fresh()->ready_at)->not->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($reservations[0]->fresh()))->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($reservations[1]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(2)
        ->and($members[0]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and($members[1]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and($members[2]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1);
});

it('moves following reservations only after the ready reservation is issued', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Issue moves queue book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $index === 0 ? $branch->id : null,
        'assigned_book_copy_id' => $index === 0 ? $copy->id : null,
        'status' => $index === 0 ? Reservation::STATUS_READY : Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(3 - $index),
        'ready_at' => $index === 0 ? now()->subMinute() : null,
        'expires_at' => $index === 0 ? now()->addDays(3) : null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[1]);
    app(ReservationNotificationService::class)->notifyCreated($reservations[2]);

    app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $members[0]->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Issuing ready reservation.',
    ]);

    $secondChange = $members[1]->notifications()->where('type', 'reservation_queue_changed')->first();
    $thirdChange = $members[2]->notifications()->where('type', 'reservation_queue_changed')->first();

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_FULFILLED)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[1]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(2)
        ->and($secondChange)->toBeNull()
        ->and($thirdChange)->toBeNull();
});

it('prepares as many first reservations as there are eligible available copies', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(4)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Two ready copies book']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->count(2)->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(4 - $index),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[2]);
    app(ReservationNotificationService::class)->notifyCreated($reservations[3]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[1]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[2]->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservations[3]->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservations[0]->fresh()->ready_at)->not->toBeNull()
        ->and($reservations[1]->fresh()->ready_at)->not->toBeNull()
        ->and($reservations[2]->fresh()->ready_at)->toBeNull()
        ->and($reservations[3]->fresh()->ready_at)->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($reservations[0]->fresh()))->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($reservations[1]->fresh()))->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[3]->fresh()))->toBe(2)
        ->and($members[2]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and($members[3]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1);
});

it('keeps a future ready reservation in the active queue', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $readyReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subMinutes(2),
        'ready_at' => now()->subMinute(),
        'expires_at' => now()->addDay(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $waitingReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    expect(app(ReservationQueueService::class)->positionFor($readyReservation))->toBeNull()
        ->and(app(ReservationQueueService::class)->positionFor($waitingReservation))->toBe(1);
});

it('sends one queue change only when each previous reservation is actually fulfilled', function () {
    $library = Library::factory()->create();
    $loanMember = User::factory()->member()->create(['library_id' => $library->id]);
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'No duplicate queue changes']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $loanMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(3 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[1]);
    app(ReservationNotificationService::class)->notifyCreated($reservations[2]);

    app(ReturnBookCopyAction::class)->handle($staff, $copy);

    expect(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(2)
        ->and($members[0]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and($members[1]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and($members[2]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1);

    app(BorrowBookCopyAction::class)->handle($staff, $copy->fresh(), [
        'user_id' => $members[0]->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Issue first ready reservation.',
    ]);

    $thirdChangesAfterFirstIssue = $members[2]->notifications()->where('type', 'reservation_queue_changed')->get();

    expect(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(2)
        ->and($members[1]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and($thirdChangesAfterFirstIssue)->toHaveCount(1)
        ->and($thirdChangesAfterFirstIssue->first()->data['metadata']['old_queue_position'])->toBe(3)
        ->and($thirdChangesAfterFirstIssue->first()->data['metadata']['new_queue_position'])->toBe(2);

    app(ReturnBookCopyAction::class)->handle($staff, $copy->fresh());

    expect(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(1)
        ->and($members[2]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(2);

    app(BorrowBookCopyAction::class)->handle($staff, $copy->fresh(), [
        'user_id' => $members[1]->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => 'Issue second ready reservation.',
    ]);

    $thirdChanges = $members[2]->notifications()->where('type', 'reservation_queue_changed')->latest()->get();

    $thirdTransitions = $thirdChanges
        ->map(fn ($notification) => $notification->data['metadata']['old_queue_position'].'->'.$notification->data['metadata']['new_queue_position'])
        ->all();

    expect(app(ReservationQueueService::class)->positionFor($reservations[2]->fresh()))->toBe(1)
        ->and($thirdChanges)->toHaveCount(2)
        ->and($thirdTransitions)->toContain('3->2')
        ->and($thirdTransitions)->toContain('2->1');
});

it('does not duplicate ready or queue changed notifications on repeated sync', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(2 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[1]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);
    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($members[0]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and($members[1]->notifications()->where('type', 'reservation_queue_changed')->count())->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[1]->fresh()))->toBe(1);
});

it('selects ready candidates from the locked reservation set instead of a late eligible query', function () {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('The active READY copy unique constraint is enforced by the MySQL/MariaDB generated column.');
    }

    $library = Library::factory()->create();
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
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
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'ready_at' => null,
        'expires_at' => null,
    ]);

    $queueService = new class($reservation) extends ReservationQueueService
    {
        public bool $insertedConflict = false;

        public function __construct(private readonly Reservation $targetReservation) {}

        public function getEligibleReservationForCopy(BookCopy $copy, array $exceptReservationIds = [], bool $lockForUpdate = false): ?Reservation
        {
            if (! $this->insertedConflict) {
                $conflictingMember = User::factory()->member()->create(['library_id' => $copy->library_id]);

                Reservation::query()->create([
                    'library_id' => $copy->library_id,
                    'book_id' => $copy->book_id,
                    'user_id' => $conflictingMember->id,
                    'scope' => Reservation::SCOPE_LIBRARY,
                    'branch_id' => null,
                    'pickup_branch_id' => $copy->branch_id,
                    'assigned_book_copy_id' => $copy->id,
                    'status' => Reservation::STATUS_READY,
                    'reserved_at' => now()->subMinutes(2),
                    'ready_at' => now()->subMinute(),
                    'expires_at' => now()->addDay(),
                ]);

                $this->insertedConflict = true;
            }

            return $this->targetReservation->fresh();
        }
    };

    $action = new SyncReservationQueueAction($queueService, app(ReservationNotificationService::class));

    $action->handle($library->id, $book->id);

    expect($queueService->insertedConflict)->toBeFalse()
        ->and($reservation->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservation->fresh()->assigned_book_copy_id)->toBe($copy->id)
        ->and(Reservation::query()->where('status', Reservation::STATUS_READY)->where('assigned_book_copy_id', $copy->id)->count())->toBe(1)
        ->and($member->notifications()->where('type', 'reservation_ready')->count())->toBe(1);
})->group('mysql', 'mariadb', 'database-invariants', 'concurrency');

it('sends one queue change when a reservation is cancelled', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $first = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($second);
    app(CancelReservationAction::class)->handle($staff, $first, 'Test cancellation.');

    $changes = $members[1]->notifications()->where('type', 'reservation_queue_changed')->get();

    expect($changes)->toHaveCount(1)
        ->and($changes->first()->data['metadata']['old_queue_position'])->toBe(2)
        ->and($changes->first()->data['metadata']['new_queue_position'])->toBe(1);
});

it('promotes the next reservation when expiring a ready reservation frees its assigned copy', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now()->subMinutes(2),
        'ready_at' => now()->subMinute(),
        'expires_at' => now()->subMinute(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyCreated($second);
    $this->artisan('reservations:expire')->assertExitCode(0);

    $changes = $members[1]->notifications()->where('type', 'reservation_queue_changed')->get();

    expect($changes)->toHaveCount(0)
        ->and($second->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and(app(ReservationQueueService::class)->positionFor($second->fresh()))->toBeNull();
});

it('uses the true global new position when the first reservation is removed from a five item queue', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $members = User::factory()->count(5)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(5 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[4]);
    app(CancelReservationAction::class)->handle($staff, $reservations[0], 'Remove first reservation.');

    $change = $members[4]->notifications()->where('type', 'reservation_queue_changed')->first();

    expect($change)->not->toBeNull()
        ->and($change->data['metadata']['old_queue_position'])->toBe(5)
        ->and($change->data['metadata']['new_queue_position'])->toBe(4)
        ->and($change->data['metadata']['new_queue_position'])->toBe(app(ReservationQueueService::class)->getQueuePosition($reservations[4]->fresh()))
        ->and($change->data['message'])->toContain('4 vietoje');
});

it('does not use the affected collection index when the second reservation is removed', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $members = User::factory()->count(5)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(5 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[2]);
    app(ReservationNotificationService::class)->notifyCreated($reservations[4]);
    app(CancelReservationAction::class)->handle($staff, $reservations[1], 'Remove second reservation.');

    $thirdChange = $members[2]->notifications()->where('type', 'reservation_queue_changed')->first();
    $fifthChange = $members[4]->notifications()->where('type', 'reservation_queue_changed')->first();

    expect($thirdChange)->not->toBeNull()
        ->and($thirdChange->data['metadata']['old_queue_position'])->toBe(3)
        ->and($thirdChange->data['metadata']['new_queue_position'])->toBe(2)
        ->and($fifthChange)->not->toBeNull()
        ->and($fifthChange->data['metadata']['old_queue_position'])->toBe(5)
        ->and($fifthChange->data['metadata']['new_queue_position'])->toBe(4);
});

it('keeps ready reservations in the position map when calculating queue changed payloads', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $members = User::factory()->count(5)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(5 - $index),
        'expires_at' => $index < 2 ? now()->addDays(2) : null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[4]);
    app(CancelReservationAction::class)->handle($staff, $reservations[0], 'Remove first ready reservation.');

    $change = $members[4]->notifications()->where('type', 'reservation_queue_changed')->first();

    expect(app(ReservationQueueService::class)->getQueuePosition($reservations[1]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($reservations[4]->fresh()))->toBe(4)
        ->and($change)->not->toBeNull()
        ->and($change->data['metadata']['old_queue_position'])->toBe(5)
        ->and($change->data['metadata']['new_queue_position'])->toBe(4);
});

it('moves waiting positions when expiring ready reservations frees assigned copies', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(5)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => $index < 2 ? Reservation::STATUS_READY : Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(5 - $index),
        'ready_at' => $index < 2 ? now()->subMinutes(4 - $index) : null,
        'expires_at' => $index < 2 ? now()->subMinute() : null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[4]);
    $this->artisan('reservations:expire')->assertExitCode(0);

    $change = $members[4]->notifications()->where('type', 'reservation_queue_changed')->first();

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_EXPIRED)
        ->and($reservations[1]->fresh()->status)->toBe(Reservation::STATUS_EXPIRED)
        ->and($reservations[2]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[3]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($change)->not->toBeNull()
        ->and($change->data['metadata']['new_queue_position'])->toBeLessThan($change->data['metadata']['old_queue_position']);
});

it('keeps notification payload in sync with web list book details api and queue service', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $members = User::factory()->count(5)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Consistent payload book']);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(5 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReservationNotificationService::class)->notifyCreated($reservations[4]);
    app(CancelReservationAction::class)->handle($staff, $reservations[0], 'Remove first reservation.');

    $target = $reservations[4]->fresh();
    $expectedPosition = app(ReservationQueueService::class)->getQueuePosition($target);
    $notification = $members[4]->notifications()->where('type', 'reservation_queue_changed')->first();
    $webReservation = collect(app(GetLibraryReservationsQuery::class)->handle($staff, ['per_page' => 10])->items())
        ->firstWhere('id', $target->id);
    $bookReservation = app(GetLibraryBookDetailsQuery::class)->handle($staff, $book)
        ->reservations
        ->firstWhere('id', $target->id);
    $apiReservation = collect($this->actingAs($staff)
        ->getJson('/api/auth/reservations?per_page=10')
        ->assertOk()
        ->json('data'))
        ->firstWhere('id', $target->id);

    expect($expectedPosition)->toBe(4)
        ->and($notification->data['metadata']['new_queue_position'])->toBe($expectedPosition)
        ->and((int) $webReservation->queue_position)->toBe($expectedPosition)
        ->and((int) $bookReservation->queue_position)->toBe($expectedPosition)
        ->and($apiReservation['queue_position'])->toBe($expectedPosition);
});

it('does not change reservation identity or creation date when marking it ready', function () {
    $library = Library::factory()->create();
    $loanMember = User::factory()->member()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
    $createdAt = now()->setDate(2026, 7, 15)->setTime(10, 24);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $loanMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => $createdAt,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReturnBookCopyAction::class)->handle($staff, $copy);

    $readyReservation = $reservation->fresh();

    expect($readyReservation->id)->toBe($reservation->id)
        ->and($readyReservation->created_at->format('Y-m-d H:i'))->toBe('2026-07-15 10:24')
        ->and($readyReservation->reserved_at->format('Y-m-d H:i'))->toBe('2026-07-15 10:24')
        ->and($readyReservation->status)->toBe(Reservation::STATUS_READY)
        ->and($readyReservation->ready_at)->not->toBeNull()
        ->and($readyReservation->expires_at)->not->toBeNull()
        ->and(app(ReservationQueueService::class)->getQueuePosition($readyReservation))->toBeNull();
});

it('keeps the first reservation first when ready assignment updates timestamps', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(4)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->setDate(2026, 7, 15)->setTime(10, 24 + $index),
        'updated_at' => now()->setDate(2026, 7, 15)->setTime(10, 24 + $index),
        'reserved_at' => $index === 0
            ? now()->setDate(2026, 7, 15)->setTime(13, 29)
            : now()->setDate(2026, 7, 15)->setTime(10, 24 + $index),
        'expires_at' => $index === 0 ? now()->addDays(7) : null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    $positions = $reservations
        ->map(fn (Reservation $reservation) => app(ReservationQueueService::class)->getQueuePosition($reservation->fresh()))
        ->all();

    expect($positions)->toBe([1, 2, 3, 4]);
});

it('does not let updated_at affect queue order', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    $first = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subHours(2),
        'reserved_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subHour(),
        'reserved_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $first->forceFill(['updated_at' => now()->addHours(3)])->save();

    expect(app(ReservationQueueService::class)->getQueuePosition($first->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($second->fresh()))->toBe(2);
});

it('uses created_at as displayed reservation date on the library reservation list', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Stable reservation date']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $createdAt = now()->setDate(2026, 7, 15)->setTime(10, 24);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => $createdAt,
        'updated_at' => now()->setDate(2026, 7, 15)->setTime(13, 29),
        'reserved_at' => now()->setDate(2026, 7, 15)->setTime(13, 29),
        'expires_at' => now()->addDays(7),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->actingAs($staff)
        ->get(route('reservations.index'))
        ->assertOk()
        ->assertSee('2026-07-15 10:24')
        ->assertDontSee('2026-07-15 13:29');
});

it('prepares a later library reservation when earlier branch reservations cannot be served by the returned copy', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->admin()->create(['library_id' => $library->id]);
    $loanMember = User::factory()->member()->create(['library_id' => $library->id]);
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $locationB = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchB->id]);
    $copyB = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copyB->id,
        'user_id' => $loanMember->id,
        'issued_by' => $staff->id,
        'status' => Loan::STATUS_ACTIVE,
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => $index < 2 ? Reservation::SCOPE_BRANCH : Reservation::SCOPE_LIBRARY,
        'branch_id' => $index < 2 ? $branchA->id : null,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinutes(3 - $index),
        'reserved_at' => now()->subMinutes(3 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(ReturnBookCopyAction::class)->handle($staff, $copyB);

    expect($reservations[0]->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservations[1]->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservations[2]->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($reservations[2]->fresh()->ready_at)->not->toBeNull()
        ->and($reservations[2]->fresh()->expires_at)->not->toBeNull()
        ->and($members[2]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($reservations[2]->fresh()))->toBeNull();
});

it('lets a library reservation use a copy from another branch when the older branch reservation is not serviceable', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $locationB = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchB->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $first = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA->id,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinutes(2),
        'reserved_at' => now()->subMinutes(2),
        'ready_at' => null,
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);
    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinute(),
        'reserved_at' => now()->subMinute(),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($first->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($first->fresh()->ready_at)->toBeNull()
        ->and($second->fresh()->status)->toBe(Reservation::STATUS_READY)
        ->and($second->fresh()->ready_at)->not->toBeNull()
        ->and($members[1]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($second->fresh()))->toBeNull();
});

it('prepares the first library reservation when a returned copy branch can serve it', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(3)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $locationB = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branchB->id]);

    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branchB->id,
        'location_id' => $locationB->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = $members->values()->map(fn (User $member, int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => $index === 1 ? Reservation::SCOPE_BRANCH : Reservation::SCOPE_LIBRARY,
        'branch_id' => $index === 1 ? $branchA->id : null,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinutes(3 - $index),
        'reserved_at' => now()->subMinutes(3 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($reservations[0]->fresh()->expires_at)->not->toBeNull()
        ->and($reservations[1]->fresh()->expires_at)->toBeNull()
        ->and($reservations[2]->fresh()->expires_at)->toBeNull()
        ->and($members[0]->notifications()->where('type', 'reservation_ready')->count())->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($reservations[0]->fresh()))->toBeNull()
        ->and(app(ReservationQueueService::class)->getQueuePosition($reservations[1]->fresh()))->toBe(1)
        ->and(app(ReservationQueueService::class)->getQueuePosition($reservations[2]->fresh()))->toBe(2);
});

it('does not send ready notification directly for a non-first reservation', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[0]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinutes(2),
        'reserved_at' => now()->subMinutes(2),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $second = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[1]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'created_at' => now()->subMinute(),
        'reserved_at' => now()->subMinute(),
        'expires_at' => now()->addDays(3),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    app(ReservationNotificationService::class)->notifyReady($second);

    expect(app(ReservationQueueService::class)->getQueuePosition($second->fresh()))->toBe(2)
        ->and($members[1]->notifications()->where('type', 'reservation_ready')->count())->toBe(0);
});
