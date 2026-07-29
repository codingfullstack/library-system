<?php

use App\Actions\Notifications\CreateUserNotificationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\LibraryNotification;
use App\Services\ReservationQueueService;
use App\Support\Notifications\NotificationType;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('expires ready reservations idempotently without duplicate notifications', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copy->id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now()->subDays(15),
        'expires_at' => now()->subMinute(),
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]);

    $this->artisan('reservations:expire')->assertSuccessful();
    $this->artisan('reservations:expire')->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_EXPIRED)
        ->and($reservation->fresh()->assigned_book_copy_id)->toBeNull()
        ->and($member->notifications()->where('type', NotificationType::RESERVATION_EXPIRED->value)->count())->toBe(1);
});

it('does not dispatch reservation side effects when the surrounding transaction rolls back', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $book = Book::factory()->create();
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    try {
        DB::transaction(function () use ($member, $reservation): void {
            DB::afterCommit(fn () => app(CreateUserNotificationAction::class)->handle(
                $member,
                null,
                NotificationType::RESERVATION_READY,
                null,
                'Rollback should suppress this notification.',
                ['reservation_id' => $reservation->id],
                Reservation::class,
                $reservation->id
            ));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // Expected rollback path.
    }

    expect($member->notifications()->count())->toBe(0);
});

it('registers reservation expiration as a non-overlapping one-server scheduler task', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event|CallbackEvent $event): bool => str_contains((string) $event->command, 'reservations:expire'));

    expect($event)->not->toBeNull()
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('configures queued library notifications with bounded retry backoff', function () {
    $notification = new LibraryNotification(
        kind: NotificationType::RESERVATION_READY,
        title: 'Rezervacija',
        message: 'Paruosta',
        relatedType: Reservation::class,
        relatedId: 123,
    );

    expect($notification->tries)->toBe(3)
        ->and($notification->backoff)->toBe([30, 120, 300]);
});

it('keeps repeated queue sync idempotent for ready and queue-change notifications', function () {
    $library = Library::factory()->create();
    $members = User::factory()->count(2)->member()->create(['library_id' => $library->id])->values();
    $book = Book::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'status' => BookCopy::STATUS_AVAILABLE,
    ]);

    $reservations = collect([0, 1])->map(fn (int $index) => Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $members[$index]->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subMinutes(2 - $index),
        'expires_at' => null,
        'fulfilled_at' => null,
        'cancelled_at' => null,
    ]));

    app(CreateUserNotificationAction::class)->handle(
        $members[1],
        null,
        NotificationType::RESERVATION_CREATED,
        null,
        'Created',
        ['queue_position' => 2, 'new_queue_position' => 2],
        Reservation::class,
        $reservations[1]->id
    );

    app(\App\Actions\Reservations\SyncReservationQueueAction::class)->handle($library->id, $book->id);
    app(\App\Actions\Reservations\SyncReservationQueueAction::class)->handle($library->id, $book->id);

    expect($members[0]->notifications()->where('type', NotificationType::RESERVATION_READY->value)->count())->toBe(1)
        ->and($members[1]->notifications()->where('type', NotificationType::RESERVATION_QUEUE_CHANGED->value)->count())->toBe(1)
        ->and(app(ReservationQueueService::class)->positionFor($reservations[1]->fresh()))->toBe(1);
});
