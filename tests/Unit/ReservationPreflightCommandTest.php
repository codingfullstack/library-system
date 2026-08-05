<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

#[Group('mysql')]
#[Group('mariadb')]
#[Group('database-invariants')]
class ReservationPreflightCommandTest extends TestCase
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

    public function test_it_returns_block_and_non_zero_exit_for_incomplete_ready_rows(): void
    {
        $this->dropReadyCompletenessCheck();

        $library = Library::factory()->create();
        $member = User::factory()->member()->create(['library_id' => $library->id]);
        $book = Book::factory()->create();

        DB::table('reservations')->insert([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'pickup_branch_id' => null,
            'assigned_book_copy_id' => null,
            'status' => Reservation::STATUS_READY,
            'reserved_at' => now(),
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('reservations:preflight-assignment-migration', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('BLOCK', $payload['status']);
        $this->assertSame('BLOCK', $payload['checks']['incomplete_ready']['level']);
    }

    public function test_it_returns_warn_and_zero_exit_for_non_blocking_expired_ready_rows(): void
    {
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

        Reservation::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => $copy->id,
            'status' => Reservation::STATUS_READY,
            'ready_at' => now()->subDays(2),
            'expires_at' => now()->subMinute(),
        ]);

        $exitCode = Artisan::call('reservations:preflight-assignment-migration', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('WARN', $payload['status']);
        $this->assertSame('WARN', $payload['checks']['expired_ready']['level']);
    }
}
