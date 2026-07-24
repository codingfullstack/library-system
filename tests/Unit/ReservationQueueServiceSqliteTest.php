<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\Library;
use App\Services\ReservationQueueService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

class ReservationQueueServiceSqliteTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_queue_unique_conflict_is_handled_and_returns_same_queue_row(): void
    {
        $library = Library::factory()->create();
        $book = Book::factory()->create();

        [$firstQueue, $secondQueue] = DB::transaction(fn (): array => [
            app(ReservationQueueService::class)->lockQueueContext($library->id, $book->id),
            app(ReservationQueueService::class)->lockQueueContext($library->id, $book->id),
        ]);

        $this->assertSame((int) $firstQueue->id, (int) $secondQueue->id);
        $this->assertDatabaseCount('reservation_queues', 1);
    }

    public function test_sqlite_foreign_key_violation_is_not_hidden(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(fn () => app(ReservationQueueService::class)->lockQueueContext(123456, 654321));
    }

    public function test_sqlite_other_unique_constraint_violation_is_not_classified_as_queue_conflict(): void
    {
        $exception = $this->sqliteConstraintException(
            'UNIQUE constraint failed: users.email'
        );

        $this->assertFalse($this->classifiesAsQueueUniqueConflict($exception));
    }

    public function test_sqlite_other_queue_constraint_violation_is_not_classified_as_queue_unique_conflict(): void
    {
        $exception = $this->sqliteConstraintException(
            'NOT NULL constraint failed: reservation_queues.library_id'
        );

        $this->assertFalse($this->classifiesAsQueueUniqueConflict($exception));
    }

    public function test_legacy_ready_apply_source_locks_queue_context_before_row_locks(): void
    {
        $contents = file_get_contents(base_path('app/Console/Commands/AuditLegacyReadyReservationsCommand.php'));
        $queueLockPosition = strpos($contents, 'lockQueueContext($libraryId, $bookId)');
        $reservationLockPosition = strpos($contents, '->lockForUpdate()', $queueLockPosition);

        $this->assertIsInt($queueLockPosition);
        $this->assertIsInt($reservationLockPosition);
        $this->assertLessThan($reservationLockPosition, $queueLockPosition);
    }

    private function sqliteConstraintException(string $message): QueryException
    {
        $previous = new PDOException($message);
        $previous->errorInfo = ['23000', 19, $message];

        return new QueryException('sqlite', 'insert into test values (?)', [], $previous);
    }

    private function classifiesAsQueueUniqueConflict(QueryException $exception): bool
    {
        $method = new ReflectionMethod(ReservationQueueService::class, 'isReservationQueueUniqueConstraintViolation');
        $method->setAccessible(true);

        return $method->invoke(app(ReservationQueueService::class), $exception);
    }
}
