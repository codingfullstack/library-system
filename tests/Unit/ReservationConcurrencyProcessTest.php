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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

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
        $this->assertLessThan(1.0, $elapsed);
        $this->assertNotSame(
            (int) file_get_contents($firstAcquiredFile),
            (int) file_get_contents($secondAcquiredFile)
        );
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
