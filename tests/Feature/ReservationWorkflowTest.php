<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('sends an internal notification when staff cancels a reservation with a reason', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Pranesimu knyga']);

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

    app(CancelReservationAction::class)->handle($staff, $reservation, 'Neradome tinkamo egzemplioriaus siandien.');

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $member->id,
        'sent_by' => $staff->id,
        'type' => 'reservation_cancelled',
        'title' => 'Rezervacija atsaukta',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reservation_cancelled',
        'auditable_type' => Reservation::class,
        'auditable_id' => $reservation->id,
    ]);
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
        'notes' => 'Skubu isduoti kitam nariui.',
        'override_reservation' => true,
        'override_reason' => 'Narys atvyko i vieta, rezervaves narys dar neatvyko.',
    ]);

    expect($result['loan'])->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'reservation_override_issued',
        'auditable_type' => Reservation::class,
        'auditable_id' => $reservation->id,
    ]);

    $this->assertDatabaseMissing('user_notifications', [
        'user_id' => $otherMember->id,
        'type' => 'reservation_fulfilled',
        'related_type' => Reservation::class,
        'related_id' => $reservation->id,
    ]);
});

it('shows notifications for the authenticated user', function () {
    $user = User::factory()->member()->create();
    $sender = User::factory()->staff()->create(['library_id' => $user->library_id]);

    $user->notifications()->create([
        'sent_by' => $sender->id,
        'type' => 'reservation_cancelled',
        'title' => 'Rezervacija atsaukta',
        'message' => 'Tavo rezervacija buvo atsaukta.',
    ]);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Pranesimai')
        ->assertSee('Rezervacija atsaukta')
        ->assertSee('Tavo rezervacija buvo atsaukta.');
});

it('creates an overdue notification when a member is at least one day late', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Veluojanti knyga']);
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
        'status' => 'overdue',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)
        ->get(route('books.index'))
        ->assertOk();

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $member->id,
        'type' => 'loan_overdue',
        'related_type' => \App\Models\Loan::class,
        'related_id' => $loan->id,
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
        'status' => 'overdue',
        'returned_at' => null,
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($member)->get(route('books.index'))->assertOk();
    $this->actingAs($member)->get(route('books.index'))->assertOk();

    expect(
        \App\Models\UserNotification::query()
            ->where('user_id', $member->id)
            ->where('type', 'loan_overdue')
            ->where('related_type', \App\Models\Loan::class)
            ->where('related_id', $loan->id)
            ->count()
    )->toBe(1);
});

it('creates a reservation ready notification for the first waiting member', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Paruosta knyga']);
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
        'status' => 'active',
        'borrowed_at' => now()->subDays(7),
        'due_at' => now()->subDay(),
        'returned_at' => null,
    ]);

    app(\App\Actions\Loans\ReturnBookCopyAction::class)->handle($staff, $bookCopy);

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $member->id,
        'type' => 'reservation_ready',
        'related_type' => Reservation::class,
        'related_id' => $reservation->id,
    ]);

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $member->id,
        'type' => 'book_returned',
        'related_type' => Loan::class,
        'related_id' => $loan->id,
    ]);
});

it('creates a reservation fulfilled notification when reserved book is issued to the same member', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Ivykdyta rezervacija']);
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
        'notes' => 'Isduota pagal rezervacija.',
    ]);

    $this->assertDatabaseHas('user_notifications', [
        'user_id' => $member->id,
        'type' => 'reservation_fulfilled',
        'related_type' => Reservation::class,
        'related_id' => $reservation->id,
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
        'notes' => 'Isduota is laisvos kopijos pagal rezervaciju eile.',
    ]);

    expect($result['loan'])->not->toBeNull();

    $this->assertDatabaseHas('book_copies', [
        'id' => $bookCopy->id,
        'status' => BookCopy::STATUS_LOANED,
    ]);
});
