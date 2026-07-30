<?php

namespace Tests\Unit;

use App\Actions\Reservations\CreateReservationAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationQueue;
use App\Models\User;
use App\Services\ReservationQueueService;
use App\Support\Notifications\NotificationType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

#[Group('mysql')]
#[Group('mariadb')]
#[Group('concurrency')]
#[Group('database-invariants')]
class ReservationConcurrencyProcessTest extends TestCase
{
    use UsesTemporaryMariaDbDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryMariaDbDatabase();
    }

    protected function tearDown(): void
    {
        Artisan::call('up');

        $this->tearDownTemporaryMariaDbDatabase();

        parent::tearDown();
    }

    public function test_two_independent_processes_do_not_assign_the_same_copy_twice(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);

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
            'status' => Reservation::STATUS_WAITING,
            'reserved_at' => now()->subMinutes(2 - $index),
            'created_at' => now()->subMinutes(2 - $index),
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]));

        $first = $this->syncProcess($library->id, $book->id);
        $second = $this->syncProcess($library->id, $book->id);

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());

        $this->assertSame(1, Reservation::query()
            ->where('status', Reservation::STATUS_READY)
            ->where('assigned_book_copy_id', $copy->id)
            ->count());

        $this->assertSame(Reservation::STATUS_READY, $reservations[0]->fresh()->status);
        $this->assertSame($copy->id, $reservations[0]->fresh()->assigned_book_copy_id);
        $this->assertSame(Reservation::STATUS_WAITING, $reservations[1]->fresh()->status);
        $this->assertNull($reservations[1]->fresh()->assigned_book_copy_id);
    }

    public function test_same_library_book_queue_context_uses_one_row_and_serializes_processes(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $releaseFile = $this->signalFile('release_same_queue');
        $firstAcquiredFile = $this->signalFile('first_acquired_same_queue');
        $secondAcquiredFile = $this->signalFile('second_acquired_same_queue');

        $first = $this->queueLockProcess($library->id, $book->id, $releaseFile, $firstAcquiredFile);
        $second = $this->queueLockProcess($library->id, $book->id, null, $secondAcquiredFile);

        $first->start();
        $this->waitForSignalFile($firstAcquiredFile);

        $startedAt = microtime(true);
        $second->start();
        usleep(300 * 1000);

        $this->assertTrue($second->isRunning(), $second->getErrorOutput().$second->getOutput());
        $this->assertFileDoesNotExist($secondAcquiredFile);

        touch($releaseFile);
        $first->wait();
        $second->wait();
        $elapsed = microtime(true) - $startedAt;

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        $this->assertFileExists($secondAcquiredFile);
        $this->assertGreaterThanOrEqual(0.25, $elapsed);

        $firstQueueId = (int) file_get_contents($firstAcquiredFile);
        $secondQueueId = (int) file_get_contents($secondAcquiredFile);

        $this->assertSame($firstQueueId, $secondQueueId);
        $this->assertSame($firstQueueId, ReservationQueue::query()
            ->where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->value('id'));
    }

    public function test_different_library_book_queue_contexts_do_not_block_each_other(): void
    {
        $library = Library::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create();
        $releaseFile = $this->signalFile('release_different_queue');
        $firstAcquiredFile = $this->signalFile('first_acquired_different_queue');
        $secondAcquiredFile = $this->signalFile('second_acquired_different_queue');

        $first = $this->queueLockProcess($library->id, $firstBook->id, $releaseFile, $firstAcquiredFile);
        $second = $this->queueLockProcess($library->id, $secondBook->id, null, $secondAcquiredFile);

        $first->start();
        $this->waitForSignalFile($firstAcquiredFile);

        $startedAt = microtime(true);
        $second->start();
        $second->wait();
        $elapsed = microtime(true) - $startedAt;

        touch($releaseFile);
        $first->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        $this->assertFileExists($secondAcquiredFile);
        $this->assertLessThan(2.0, $elapsed);
        $this->assertNotSame(
            (int) file_get_contents($firstAcquiredFile),
            (int) file_get_contents($secondAcquiredFile)
        );
    }

    public function test_different_queues_do_not_block_on_reservation_or_copy_row_locks(): void
    {
        $library = Library::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $members = User::factory()->count(2)->member()->create(['library_id' => $library->id]);

        foreach ([$firstBook, $secondBook] as $index => $book) {
            BookCopy::factory()->create([
                'library_id' => $library->id,
                'book_id' => $book->id,
                'branch_id' => $branch->id,
                'location_id' => $location->id,
                'status' => BookCopy::STATUS_AVAILABLE,
            ]);

            Reservation::factory()->create([
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $members[$index]->id,
                'scope' => Reservation::SCOPE_LIBRARY,
                'branch_id' => null,
                'status' => Reservation::STATUS_WAITING,
                'reserved_at' => now()->subMinutes(2 - $index),
                'ready_at' => null,
                'expires_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => null,
            ]);
        }

        $releaseFile = $this->signalFile('release_queue_a_rows');
        $lockedFile = $this->signalFile('queue_a_rows_locked');
        $firstQueueLocks = $this->queueMutationRowsLockProcess($library->id, $firstBook->id, $releaseFile, $lockedFile);
        $secondQueueSync = $this->syncProcess($library->id, $secondBook->id);

        $firstQueueLocks->start();
        $this->waitForSignalFile($lockedFile);

        $startedAt = microtime(true);
        $secondQueueSync->start();
        $secondQueueSync->wait();
        $elapsed = microtime(true) - $startedAt;

        touch($releaseFile);
        $firstQueueLocks->wait();

        $this->assertTrue($firstQueueLocks->isSuccessful(), $firstQueueLocks->getErrorOutput().$firstQueueLocks->getOutput());
        $this->assertTrue($secondQueueSync->isSuccessful(), $secondQueueSync->getErrorOutput().$secondQueueSync->getOutput());
        $this->assertLessThan(2.0, $elapsed);
    }

    public function test_production_queue_lock_call_sites_do_not_target_multiple_queue_identities(): void
    {
        $callSites = [
            base_path('app/Actions/Loans/BorrowBookCopyAction.php') => [
                '$bookCopyContext->library_id',
                '$bookCopyContext->book_id',
                'handle($bookCopy->library_id, $bookCopy->book_id)',
            ],
            base_path('app/Actions/Loans/ReturnBookCopyAction.php') => [
                '$bookCopyContext->library_id',
                '$bookCopyContext->book_id',
                'handle($bookCopy->library_id, $bookCopy->book_id)',
            ],
            base_path('app/Actions/Reservations/CancelReservationAction.php') => [
                '$reservationContext->library_id',
                '$reservationContext->book_id',
                'handle($lockedReservation->library_id, $lockedReservation->book_id)',
            ],
            base_path('app/Actions/Reservations/CreateReservationAction.php') => [
                'lockQueueContext($libraryId, (int) $book->id)',
                'handle($libraryId, $book->id)',
            ],
            base_path('app/Console/Commands/ExpireReservationsCommand.php') => [
                '$reservationContext->library_id',
                '$reservationContext->book_id',
                'handle($bookToSync[\'library_id\'], $bookToSync[\'book_id\'])',
            ],
        ];

        foreach ($callSites as $path => $expectedFragments) {
            $contents = file_get_contents($path);

            $this->assertSame(1, substr_count($contents, 'lockQueueContext('), $path);

            foreach ($expectedFragments as $fragment) {
                $this->assertStringContainsString($fragment, $contents, $path);
            }
        }
    }

    public function test_legacy_apply_and_same_queue_sync_share_queue_mutex_without_deadlock(): void
    {
        $this->dropReadyCompletenessCheck();

        [$library, $book] = $this->seedLegacyAssignableReadyReservation();

        $legacyApply = $this->legacyReadyApplyProcess();
        $sync = $this->syncProcess($library->id, $book->id);

        $legacyApply->start();
        $sync->start();
        $legacyApply->wait();
        $sync->wait();

        $this->assertTrue($legacyApply->isSuccessful(), $legacyApply->getErrorOutput().$legacyApply->getOutput());
        $this->assertTrue($sync->isSuccessful(), $sync->getErrorOutput().$sync->getOutput());
        $this->assertSame(1, Reservation::query()
            ->where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->where('status', Reservation::STATUS_READY)
            ->whereNotNull('assigned_book_copy_id')
            ->count());
    }

    public function test_legacy_apply_on_queue_a_does_not_block_sync_on_queue_b(): void
    {
        $this->dropReadyCompletenessCheck();

        $library = Library::factory()->create();
        $firstBook = Book::factory()->create();
        $secondBook = Book::factory()->create();

        $this->seedLegacyAssignableReadyReservation($library, $firstBook);
        $this->seedWaitingReservationWithAvailableCopy($library, $secondBook);

        $legacyApply = $this->legacyReadyApplyProcess();
        $sync = $this->syncProcess($library->id, $secondBook->id);

        $legacyApply->start();

        $startedAt = microtime(true);
        $sync->start();
        $sync->wait();
        $elapsed = microtime(true) - $startedAt;

        $legacyApply->wait();

        $this->assertTrue($legacyApply->isSuccessful(), $legacyApply->getErrorOutput().$legacyApply->getOutput());
        $this->assertTrue($sync->isSuccessful(), $sync->getErrorOutput().$sync->getOutput());
        $this->assertLessThan(2.0, $elapsed);
    }

    public function test_queue_context_can_be_locked_when_book_has_no_copies_but_reservation_creation_is_rejected(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        $queue = DB::transaction(fn () => app(ReservationQueueService::class)
            ->lockQueueContext($library->id, $book->id));

        $this->assertSame($library->id, (int) $queue->library_id);
        $this->assertSame($book->id, (int) $queue->book_id);
        $this->assertSame(0, BookCopy::query()
            ->where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->count());

        try {
            app(CreateReservationAction::class)->handle($staff, [
                'book_id' => $book->id,
                'user_id' => $member->id,
                'scope' => Reservation::SCOPE_LIBRARY,
            ]);

            $this->fail('Reservation creation should reject books without library copies.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('book_id', $exception->errors());
        }
    }

    public function test_two_independent_processes_do_not_create_duplicate_active_reservations(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_LOANED,
        ]);

        $first = $this->createReservationProcess($staff->id, $member->id, $book->id);
        $second = $this->createReservationProcess($staff->id, $member->id, $book->id);

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());

        $outputs = [$first->getOutput(), $second->getOutput()];

        $this->assertContains('created', $outputs);
        $this->assertContains('validation', $outputs);
        $this->assertSame(1, Reservation::query()
            ->where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->where('user_id', $member->id)
            ->active()
            ->count());
    }

    public function test_return_and_ready_cancel_can_overlap_without_duplicate_ready_assignment_or_active_loans(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $staff->libraryMemberships()->where('library_id', $library->id)->update(['branch_id' => $branch->id]);
        $borrower = User::factory()->member()->create(['library_id' => $library->id]);
        $reservationMember = User::factory()->member()->create(['library_id' => $library->id]);

        $returningCopy = BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_LOANED,
        ]);
        $readyCopy = BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);

        Loan::factory()->create([
            'library_id' => $library->id,
            'book_copy_id' => $returningCopy->id,
            'user_id' => $borrower->id,
            'status' => Loan::STATUS_ACTIVE,
            'returned_at' => null,
        ]);

        $readyReservation = Reservation::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $reservationMember->id,
            'scope' => Reservation::SCOPE_LIBRARY,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => $readyCopy->id,
            'status' => Reservation::STATUS_READY,
            'ready_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        $return = $this->returnCopyProcess($staff->id, $returningCopy->id);
        $cancel = $this->cancelReservationProcess($staff->id, $readyReservation->id, 'parallel cancel');

        $return->start();
        $cancel->start();
        $return->wait();
        $cancel->wait();

        $this->assertTrue($return->isSuccessful(), $return->getErrorOutput().$return->getOutput());
        $this->assertTrue($cancel->isSuccessful(), $cancel->getErrorOutput().$cancel->getOutput());
        $this->assertSame(0, Loan::query()->whereIn('book_copy_id', [$returningCopy->id, $readyCopy->id])->active()->count());
        $this->assertSame(0, Reservation::query()->whereKey($readyReservation->id)->where('status', Reservation::STATUS_READY)->count());
        $this->assertSame(
            Reservation::query()
                ->where('library_id', $library->id)
                ->where('book_id', $book->id)
                ->where('status', Reservation::STATUS_READY)
                ->whereNotNull('assigned_book_copy_id')
                ->distinct('assigned_book_copy_id')
                ->count('assigned_book_copy_id'),
            Reservation::query()
                ->where('library_id', $library->id)
                ->where('book_id', $book->id)
                ->where('status', Reservation::STATUS_READY)
                ->whereNotNull('assigned_book_copy_id')
                ->count()
        );
    }

    public function test_expire_and_borrow_same_ready_reservation_do_not_create_active_loan(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
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
            'scope' => Reservation::SCOPE_LIBRARY,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => $copy->id,
            'status' => Reservation::STATUS_READY,
            'ready_at' => now()->subDays(15),
            'expires_at' => now()->subMinute(),
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        $expire = $this->expireReservationsProcess();
        $borrow = $this->borrowCopyProcess($staff->id, $member->id, $copy->id);

        $expire->start();
        $borrow->start();
        $expire->wait();
        $borrow->wait();

        $this->assertTrue($expire->isSuccessful(), $expire->getErrorOutput().$expire->getOutput());
        $this->assertTrue($borrow->isSuccessful(), $borrow->getErrorOutput().$borrow->getOutput());
        $this->assertSame(Reservation::STATUS_EXPIRED, $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->assigned_book_copy_id);
        $this->assertSame(0, Loan::query()->where('book_copy_id', $copy->id)->active()->count());
    }

    public function test_ready_cancel_and_borrow_same_copy_leave_one_terminal_outcome(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
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
            'scope' => Reservation::SCOPE_LIBRARY,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => $copy->id,
            'status' => Reservation::STATUS_READY,
            'ready_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        $cancel = $this->cancelReservationProcess($staff->id, $reservation->id, 'parallel cancel');
        $borrow = $this->borrowCopyProcess($staff->id, $member->id, $copy->id);

        $cancel->start();
        $borrow->start();
        $cancel->wait();
        $borrow->wait();

        $this->assertTrue($cancel->isSuccessful(), $cancel->getErrorOutput().$cancel->getOutput());
        $this->assertTrue($borrow->isSuccessful(), $borrow->getErrorOutput().$borrow->getOutput());

        $fresh = $reservation->fresh();
        $this->assertContains($fresh->status, [Reservation::STATUS_CANCELLED, Reservation::STATUS_FULFILLED]);
        $this->assertSame($fresh->status === Reservation::STATUS_FULFILLED ? 1 : 0, Loan::query()->where('book_copy_id', $copy->id)->active()->count());
        $this->assertSame(0, Reservation::query()->where('assigned_book_copy_id', $copy->id)->where('status', Reservation::STATUS_READY)->count());
    }

    public function test_two_copies_can_be_returned_concurrently_without_active_loan_or_ready_duplicates(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $staff->libraryMemberships()->where('library_id', $library->id)->update(['branch_id' => $branch->id]);
        $members = User::factory()->count(4)->member()->create(['library_id' => $library->id])->values();

        $copies = collect([1, 2])->map(fn (int $index) => BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_LOANED,
        ]));

        foreach ($copies as $index => $copy) {
            Loan::factory()->create([
                'library_id' => $library->id,
                'book_copy_id' => $copy->id,
                'user_id' => $members[$index]->id,
                'status' => Loan::STATUS_ACTIVE,
                'returned_at' => null,
            ]);
        }

        foreach ([0, 1] as $index) {
            Reservation::factory()->create([
                'library_id' => $library->id,
                'book_id' => $book->id,
                'user_id' => $members[$index + 2]->id,
                'scope' => Reservation::SCOPE_LIBRARY,
                'status' => Reservation::STATUS_WAITING,
                'reserved_at' => now()->subMinutes(2 - $index),
                'expires_at' => null,
                'fulfilled_at' => null,
                'cancelled_at' => null,
            ]);
        }

        $first = $this->returnCopyProcess($staff->id, $copies[0]->id);
        $second = $this->returnCopyProcess($staff->id, $copies[1]->id);

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        $this->assertSame(0, Loan::query()->whereIn('book_copy_id', $copies->pluck('id'))->active()->count());
        $this->assertSame(2, Reservation::query()->where('status', Reservation::STATUS_READY)->count());
        $this->assertSame(2, Reservation::query()->where('status', Reservation::STATUS_READY)->distinct('assigned_book_copy_id')->count('assigned_book_copy_id'));
    }

    public function test_related_notification_creation_is_serialized_for_duplicate_retry(): void
    {
        $library = Library::factory()->create();
        $member = User::factory()->member()->create(['library_id' => $library->id]);
        $book = Book::factory()->create();
        $reservation = Reservation::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'status' => Reservation::STATUS_WAITING,
        ]);

        $first = $this->relatedNotificationProcess($member->id, $reservation->id);
        $second = $this->relatedNotificationProcess($member->id, $reservation->id);

        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        $this->assertSame(1, $member->notifications()
            ->where('type', NotificationType::RESERVATION_READY->value)
            ->where('data->related_type', Reservation::class)
            ->where('data->related_id', $reservation->id)
            ->count());
    }

    public function test_creating_reservation_does_not_lock_same_user_loans_from_another_queue(): void
    {
        $library = Library::factory()->create();
        $queueABook = Book::factory()->create();
        $queueBBook = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $queueABook->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_LOANED,
        ]);

        $queueBCopy = BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $queueBBook->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_LOANED,
        ]);

        $queueBLoan = Loan::factory()->create([
            'library_id' => $library->id,
            'book_copy_id' => $queueBCopy->id,
            'user_id' => $member->id,
            'status' => Loan::STATUS_ACTIVE,
            'returned_at' => null,
        ]);

        $releaseFile = $this->signalFile('release_queue_b_loan');
        $loanLockedFile = $this->signalFile('queue_b_loan_locked');
        $loanLock = $this->loanLockProcess($queueBLoan->id, $releaseFile, $loanLockedFile);
        $createReservation = $this->createReservationProcess($staff->id, $member->id, $queueABook->id);

        $loanLock->start();
        $this->waitForSignalFile($loanLockedFile);

        $startedAt = microtime(true);
        $createReservation->start();
        $createReservation->wait();
        $elapsed = microtime(true) - $startedAt;

        touch($releaseFile);
        $loanLock->wait();

        $this->assertTrue($loanLock->isSuccessful(), $loanLock->getErrorOutput().$loanLock->getOutput());
        $this->assertTrue($createReservation->isSuccessful(), $createReservation->getErrorOutput().$createReservation->getOutput());
        $this->assertSame('created', $createReservation->getOutput());
        $this->assertLessThan(1.0, $elapsed);
    }

    public function test_sync_candidate_reservation_set_is_locked_before_copy_assignment(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

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
            'status' => Reservation::STATUS_WAITING,
            'reserved_at' => now()->subHour(),
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        $releaseFile = $this->signalFile('release_candidate_set');
        $candidateLockedFile = $this->signalFile('candidate_set_locked');
        $updateFinishedFile = $this->signalFile('candidate_update_finished');
        $candidateLock = $this->candidateReservationSetLockProcess($library->id, $book->id, $releaseFile, $candidateLockedFile);
        $reservationUpdate = $this->reservationStatusUpdateProcess($reservation->id, $updateFinishedFile);

        $candidateLock->start();
        $this->waitForSignalFile($candidateLockedFile);

        $reservationUpdate->start();
        usleep(300 * 1000);

        $this->assertTrue($reservationUpdate->isRunning(), $reservationUpdate->getErrorOutput().$reservationUpdate->getOutput());
        $this->assertFileDoesNotExist($updateFinishedFile);

        touch($releaseFile);
        $candidateLock->wait();
        $reservationUpdate->wait();

        $this->assertTrue($candidateLock->isSuccessful(), $candidateLock->getErrorOutput().$candidateLock->getOutput());
        $this->assertTrue($reservationUpdate->isSuccessful(), $reservationUpdate->getErrorOutput().$reservationUpdate->getOutput());
        $this->assertFileExists($updateFinishedFile);
    }

    public function test_one_waiting_reservation_is_not_assigned_to_two_available_copies(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        $copies = BookCopy::factory()->count(2)->create([
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
            'status' => Reservation::STATUS_WAITING,
            'reserved_at' => now()->subHour(),
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        $this->syncProcess($library->id, $book->id)->mustRun();

        $reservation->refresh();

        $this->assertSame(Reservation::STATUS_READY, $reservation->status);
        $this->assertContains($reservation->assigned_book_copy_id, $copies->pluck('id')->all());
        $this->assertSame(1, Reservation::query()
            ->where('library_id', $library->id)
            ->where('book_id', $book->id)
            ->where('status', Reservation::STATUS_READY)
            ->count());
        $this->assertSame(1, BookCopy::query()
            ->whereIn('id', $copies->pluck('id'))
            ->whereDoesntHave('activeReadyReservation')
            ->count());
    }

    private function syncProcess(int $libraryId, int $bookId): Process
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'reservations:sync-queue',
            (string) $libraryId,
            (string) $bookId,
            '--delay-ms=200',
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function legacyReadyApplyProcess(): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    Illuminate\Support\Facades\Artisan::call('down');

    $exitCode = Illuminate\Support\Facades\Artisan::call('reservations:audit-legacy-ready', [
        '--apply' => true,
        '--maintenance-confirmed' => true,
        '--json' => true,
    ]);

    echo Illuminate\Support\Facades\Artisan::output();

    if ($exitCode !== 0) {
        exit($exitCode);
    }
} finally {
    Illuminate\Support\Facades\Artisan::call('up');
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function createReservationProcess(int $actorId, int $memberId, int $bookId): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Actions\Reservations\CreateReservationAction::class)->handle(
        App\Models\User::query()->findOrFail((int) $argv[1]),
        [
            'book_id' => (int) $argv[3],
            'user_id' => (int) $argv[2],
            'scope' => App\Models\Reservation::SCOPE_LIBRARY,
        ],
    );

    echo 'created';
} catch (Illuminate\Validation\ValidationException) {
    echo 'validation';
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $actorId,
            (string) $memberId,
            (string) $bookId,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function borrowCopyProcess(int $actorId, int $memberId, int $copyId): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Actions\Loans\BorrowBookCopyAction::class)->handle(
        App\Models\User::query()->findOrFail((int) $argv[1]),
        App\Models\BookCopy::query()->withoutGlobalScope('library')->findOrFail((int) $argv[3]),
        [
            'user_id' => (int) $argv[2],
            'due_at' => now()->addDays(14)->toDateString(),
            'no_due_date' => false,
        ],
    );

    echo 'borrowed';
} catch (Illuminate\Validation\ValidationException) {
    echo 'validation';
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $actorId,
            (string) $memberId,
            (string) $copyId,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function returnCopyProcess(int $actorId, int $copyId): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Actions\Loans\ReturnBookCopyAction::class)->handle(
        App\Models\User::query()->findOrFail((int) $argv[1]),
        App\Models\BookCopy::query()->withoutGlobalScope('library')->findOrFail((int) $argv[2]),
    );

    echo 'returned';
} catch (Illuminate\Validation\ValidationException) {
    echo 'validation';
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $actorId,
            (string) $copyId,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function cancelReservationProcess(int $actorId, int $reservationId, string $reason): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Actions\Reservations\CancelReservationAction::class)->handle(
        App\Models\User::query()->findOrFail((int) $argv[1]),
        App\Models\Reservation::query()->withoutGlobalScope('library')->findOrFail((int) $argv[2]),
        (string) $argv[3],
    );

    echo 'cancelled';
} catch (Illuminate\Validation\ValidationException) {
    echo 'validation';
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $actorId,
            (string) $reservationId,
            $reason,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function expireReservationsProcess(): Process
    {
        $process = new Process([
            PHP_BINARY,
            'artisan',
            'reservations:expire',
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function relatedNotificationProcess(int $memberId, int $reservationId): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app(App\Actions\Notifications\CreateUserNotificationAction::class)->handle(
    App\Models\User::query()->findOrFail((int) $argv[1]),
    null,
    App\Support\Notifications\NotificationType::RESERVATION_READY,
    null,
    'Paralelinis retry',
    ['reservation_id' => (int) $argv[2]],
    App\Models\Reservation::class,
    (int) $argv[2],
);

echo 'notified';
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $memberId,
            (string) $reservationId,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function queueLockProcess(int $libraryId, int $bookId, ?string $releaseFile = null, ?string $acquiredFile = null): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();

try {
    $queue = app(App\Services\ReservationQueueService::class)->lockQueueContext((int) $argv[1], (int) $argv[2]);
    echo 'locked:'.$queue->id.PHP_EOL;
    flush();

    if ($argv[4] !== '') {
        file_put_contents($argv[4], (string) $queue->id);
    }

    if ($argv[3] !== '') {
        $deadline = microtime(true) + 10;

        while (! file_exists($argv[3])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Timed out waiting for release signal.');
            }

            usleep(20 * 1000);
        }
    }

    Illuminate\Support\Facades\DB::commit();
} catch (Throwable $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $libraryId,
            (string) $bookId,
            $releaseFile ?? '',
            $acquiredFile ?? '',
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function loanLockProcess(int $loanId, string $releaseFile, string $lockedFile): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();

try {
    App\Models\Loan::query()->whereKey((int) $argv[1])->lockForUpdate()->firstOrFail();
    file_put_contents($argv[3], 'locked');

    $deadline = microtime(true) + 10;

    while (! file_exists($argv[2])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for release signal.');
        }

        usleep(20 * 1000);
    }

    Illuminate\Support\Facades\DB::commit();
} catch (Throwable $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $loanId,
            $releaseFile,
            $lockedFile,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    /**
     * @return array{0: Library, 1: Book}
     */
    private function seedLegacyAssignableReadyReservation(?Library $library = null, ?Book $book = null): array
    {
        $library ??= Library::factory()->create();
        $book ??= Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        BookCopy::factory()->create([
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
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => null,
            'status' => Reservation::STATUS_READY,
            'reserved_at' => now()->subHour(),
            'ready_at' => now()->subMinutes(30),
            'expires_at' => now()->addDay(),
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);

        return [$library, $book];
    }

    private function seedWaitingReservationWithAvailableCopy(Library $library, Book $book): void
    {
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $member = User::factory()->member()->create(['library_id' => $library->id]);

        BookCopy::factory()->create([
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
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'status' => Reservation::STATUS_WAITING,
            'reserved_at' => now()->subHour(),
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ]);
    }

    private function candidateReservationSetLockProcess(int $libraryId, int $bookId, string $releaseFile, string $lockedFile): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();

try {
    app(App\Services\ReservationQueueService::class)->lockQueueContext((int) $argv[1], (int) $argv[2]);
    app(App\Services\ReservationQueueService::class)
        ->activeReservationsQuery((int) $argv[1], (int) $argv[2])
        ->lockForUpdate()
        ->get();
    file_put_contents($argv[4], 'locked');

    $deadline = microtime(true) + 10;

    while (! file_exists($argv[3])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for release signal.');
        }

        usleep(20 * 1000);
    }

    Illuminate\Support\Facades\DB::commit();
} catch (Throwable $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $libraryId,
            (string) $bookId,
            $releaseFile,
            $lockedFile,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function queueMutationRowsLockProcess(int $libraryId, int $bookId, string $releaseFile, string $lockedFile): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::beginTransaction();

try {
    app(App\Services\ReservationQueueService::class)->lockQueueContext((int) $argv[1], (int) $argv[2]);
    app(App\Services\ReservationQueueService::class)
        ->activeReservationsQuery((int) $argv[1], (int) $argv[2])
        ->lockForUpdate()
        ->get();
    app(App\Services\ReservationQueueService::class)
        ->availableCopiesQuery((int) $argv[1], (int) $argv[2])
        ->whereNotNull('branch_id')
        ->orderBy('branch_id')
        ->orderBy('id')
        ->lockForUpdate()
        ->get();

    file_put_contents($argv[4], 'locked');

    $deadline = microtime(true) + 10;

    while (! file_exists($argv[3])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for release signal.');
        }

        usleep(20 * 1000);
    }

    Illuminate\Support\Facades\DB::commit();
} catch (Throwable $exception) {
    Illuminate\Support\Facades\DB::rollBack();
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $libraryId,
            (string) $bookId,
            $releaseFile,
            $lockedFile,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function reservationStatusUpdateProcess(int $reservationId, string $finishedFile): Process
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\DB::transaction(function () {
    App\Models\Reservation::query()
        ->whereKey((int) $_SERVER['argv'][1])
        ->update(['notes' => 'candidate lock waited']);
});

file_put_contents($_SERVER['argv'][2], 'updated');
PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $code,
            (string) $reservationId,
            $finishedFile,
        ], base_path(), $this->temporaryDatabaseEnvironment());

        $process->setTimeout(60);

        return $process;
    }

    private function signalFile(string $name): string
    {
        $directory = storage_path('framework/testing');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory.'/'.$name.'_'.getmypid().'_'.bin2hex(random_bytes(4)).'.signal';
    }

    private function waitForSignalFile(string $path): void
    {
        $deadline = microtime(true) + 10;

        while (! file_exists($path)) {
            if (microtime(true) > $deadline) {
                $this->fail("Timed out waiting for signal file [{$path}].");
            }

            usleep(20 * 1000);
        }
    }
}
