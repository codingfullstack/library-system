<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Symfony\Component\Process\Process;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

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
}
