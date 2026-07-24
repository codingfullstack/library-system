<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

#[Group('mariadb')]
#[Group('database-invariants')]
class ReservationLegacyReadyAuditCommandTest extends TestCase
{
    use UsesTemporaryMariaDbDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTemporaryMariaDbDatabase();
        $this->dropReadyCompletenessCheck();
    }

    protected function tearDown(): void
    {
        Artisan::call('up');

        $this->tearDownTemporaryMariaDbDatabase();

        parent::tearDown();
    }

    public function test_it_classifies_legacy_ready_rows_without_applying_changes(): void
    {
        $ids = $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('WARN', $payload['status']);
        $this->assertSame(1, $payload['categories']['missing_pickup_branch']);
        $this->assertSame(1, $payload['categories']['expired']);
        $this->assertSame(1, $payload['categories']['already_fulfilled_or_returned']);
        $this->assertSame(1, $payload['categories']['no_available_copy']);
        $this->assertSame(1, $payload['categories']['active_loan_conflict']);
        $this->assertSame(1, $payload['categories']['assignable_single_copy']);
        $this->assertSame(1, $payload['categories']['assignable_multiple_candidates']);

        foreach ($ids as $id) {
            $this->assertDatabaseHas('reservations', [
                'id' => $id,
                'assigned_book_copy_id' => null,
            ]);
        }
    }

    public function test_it_audits_legacy_ready_rows_before_the_assigned_copy_column_exists(): void
    {
        $this->dropAssignedCopyMigrationColumns();
        $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertFalse($payload['assigned_book_copy_column_exists']);
        $this->assertSame('WARN', $payload['status']);
        $this->assertSame(1, $payload['categories']['assignable_single_copy']);
        $this->assertSame(1, $payload['categories']['missing_pickup_branch']);
    }

    public function test_apply_is_blocked_before_the_assigned_copy_column_exists(): void
    {
        Artisan::call('down');

        $this->dropAssignedCopyMigrationColumns();
        $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', [
            '--apply' => true,
            '--maintenance-confirmed' => true,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('BLOCK', $payload['status']);
        $this->assertSame(
            'Cannot apply assignments before reservations.assigned_book_copy_id exists.',
            $payload['apply_error']
        );
    }

    public function test_apply_is_blocked_when_application_is_not_in_maintenance_mode(): void
    {
        $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', [
            '--apply' => true,
            '--maintenance-confirmed' => true,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('BLOCK', $payload['status']);
        $this->assertSame('Apply mode requires Laravel maintenance mode.', $payload['apply_error']);
    }

    public function test_apply_is_blocked_without_explicit_maintenance_confirmation(): void
    {
        Artisan::call('down');
        $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', [
            '--apply' => true,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('BLOCK', $payload['status']);
        $this->assertSame('Apply mode requires --maintenance-confirmed.', $payload['apply_error']);
    }

    public function test_apply_assigns_only_deterministic_single_copy_rows(): void
    {
        Artisan::call('down');

        $ids = $this->seedLegacyReadyDataset();

        $exitCode = Artisan::call('reservations:audit-legacy-ready', [
            '--apply' => true,
            '--maintenance-confirmed' => true,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame([$ids['assignable_single_copy']], $payload['applied_reservation_ids']);

        $this->assertNotNull(Reservation::query()->findOrFail($ids['assignable_single_copy'])->assigned_book_copy_id);

        foreach (array_diff_key($ids, ['assignable_single_copy' => true]) as $id) {
            $this->assertNull(Reservation::query()->findOrFail($id)->assigned_book_copy_id);
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedLegacyReadyDataset(): array
    {
        $library = Library::factory()->create();
        $member = User::factory()->member()->create(['library_id' => $library->id]);
        $expiredBranch = Branch::factory()->create(['library_id' => $library->id]);
        $fulfilledBranch = Branch::factory()->create(['library_id' => $library->id]);
        $noCopyBranch = Branch::factory()->create(['library_id' => $library->id]);

        return [
            'missing_pickup_branch' => $this->legacyReady($library, $member, pickupBranch: null),
            'expired' => $this->legacyReady($library, $member, pickupBranch: $expiredBranch, expiresAt: now()->subMinute()),
            'already_fulfilled_or_returned' => $this->legacyReady($library, $member, pickupBranch: $fulfilledBranch, fulfilledAt: now()->subMinute()),
            'no_available_copy' => $this->legacyReady($library, $member, pickupBranch: $noCopyBranch),
            'active_loan_conflict' => $this->legacyReadyWithCopies($library, $member, 1, withActiveLoan: true),
            'assignable_single_copy' => $this->legacyReadyWithCopies($library, $member, 1),
            'assignable_multiple_candidates' => $this->legacyReadyWithCopies($library, $member, 2),
        ];
    }

    private function legacyReadyWithCopies(Library $library, User $member, int $copyCount, bool $withActiveLoan = false): int
    {
        $book = Book::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $copies = BookCopy::factory()->count($copyCount)->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);

        if ($withActiveLoan) {
            Loan::factory()->create([
                'library_id' => $library->id,
                'book_copy_id' => $copies->first()->id,
                'user_id' => $member->id,
                'status' => Loan::STATUS_ACTIVE,
                'returned_at' => null,
            ]);
        }

        return $this->legacyReady($library, $member, $book, $branch);
    }

    private function legacyReady(
        Library $library,
        User $member,
        ?Book $book = null,
        ?Branch $pickupBranch = null,
        mixed $expiresAt = null,
        mixed $fulfilledAt = null
    ): int {
        $book ??= Book::factory()->create();
        $expiresAt ??= now()->addDay();

        $row = [
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'scope' => Reservation::SCOPE_LIBRARY,
            'branch_id' => null,
            'pickup_branch_id' => $pickupBranch?->id,
            'status' => Reservation::STATUS_READY,
            'reserved_at' => now()->subHour(),
            'ready_at' => now()->subMinutes(30),
            'expires_at' => $expiresAt,
            'fulfilled_at' => $fulfilledAt,
            'cancelled_at' => null,
            'notes' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ];

        if (Schema::hasColumn('reservations', 'assigned_book_copy_id')) {
            $row['assigned_book_copy_id'] = null;
        }

        return (int) DB::table('reservations')->insertGetId($row);
    }

    private function dropAssignedCopyMigrationColumns(): void
    {
        foreach ([
            'ALTER TABLE reservations DROP INDEX reservations_active_ready_book_copy_unique',
            'ALTER TABLE reservations DROP COLUMN active_ready_book_copy_id',
            'ALTER TABLE reservations DROP FOREIGN KEY reservations_assigned_book_copy_id_foreign',
            'ALTER TABLE reservations DROP COLUMN assigned_book_copy_id',
        ] as $statement) {
            try {
                DB::statement($statement);
            } catch (\Throwable) {
                // The command must support databases at different points in the migration chain.
            }
        }
    }
}
