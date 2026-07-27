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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesTemporaryMariaDbDatabase;
use Tests\TestCase;

class TenantOwnershipConstraintMariaDbTest extends TestCase
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

    public function test_schema_contains_tenant_composite_constraints(): void
    {
        $constraints = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->pluck('CONSTRAINT_NAME')
            ->all();

        $this->assertContains('book_copies_branch_library_fk', $constraints);
        $this->assertContains('loans_user_membership_fk', $constraints);
        $this->assertContains('reservations_assigned_copy_library_fk', $constraints);
        $this->assertContains('scan_logs_book_copy_library_fk', $constraints);
    }

    public function test_valid_same_library_relationships_are_accepted(): void
    {
        $graph = $this->tenantGraph();
        $reservationCopy = BookCopy::factory()->create([
            'library_id' => $graph['library']->id,
            'book_id' => $graph['book']->id,
            'branch_id' => $graph['branch']->id,
            'location_id' => $graph['location']->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);
        $reservationMember = User::factory()->member()->create(['library_id' => $graph['library']->id]);
        $superAdmin = User::factory()->superAdmin()->create();

        $loanId = DB::table('loans')->insertGetId([
            'library_id' => $graph['library']->id,
            'book_copy_id' => $graph['copy']->id,
            'user_id' => $reservationMember->id,
            'issued_by' => $superAdmin->id,
            'received_by' => null,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
            'returned_at' => null,
            'status' => Loan::STATUS_ACTIVE,
            'renewal_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservationId = DB::table('reservations')->insertGetId([
            'library_id' => $graph['library']->id,
            'book_id' => $graph['book']->id,
            'user_id' => $reservationMember->id,
            'scope' => Reservation::SCOPE_BRANCH,
            'branch_id' => $graph['branch']->id,
            'pickup_branch_id' => $graph['branch']->id,
            'assigned_book_copy_id' => $reservationCopy->id,
            'status' => Reservation::STATUS_READY,
            'reserved_at' => now(),
            'ready_at' => now(),
            'expires_at' => now()->addDays(7),
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('loans', ['id' => $loanId]);
        $this->assertDatabaseHas('reservations', ['id' => $reservationId]);
    }

    public function test_cross_tenant_insert_is_rejected(): void
    {
        $graph = $this->tenantGraph();

        $this->expectException(QueryException::class);

        DB::table('book_copies')->insert([
            'library_id' => $graph['library']->id,
            'book_id' => $graph['book']->id,
            'branch_id' => $graph['otherBranch']->id,
            'location_id' => null,
            'inventory_code' => 'BAD-INSERT',
            'qr_code' => 'BAD-INSERT-QR',
            'barcode' => null,
            'status' => BookCopy::STATUS_AVAILABLE,
            'condition_status' => BookCopy::CONDITION_GOOD,
            'acquired_at' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cross_tenant_update_is_rejected(): void
    {
        $graph = $this->tenantGraph();

        $this->expectException(QueryException::class);

        DB::table('reservations')
            ->where('id', $graph['reservation']->id)
            ->update(['assigned_book_copy_id' => $graph['otherCopy']->id]);
    }

    public function test_user_without_library_membership_is_rejected(): void
    {
        $graph = $this->tenantGraph();

        $this->expectException(QueryException::class);

        DB::table('loans')->insert([
            'library_id' => $graph['library']->id,
            'book_copy_id' => $graph['copy']->id,
            'user_id' => $graph['otherMember']->id,
            'issued_by' => null,
            'received_by' => null,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(14),
            'returned_at' => null,
            'status' => Loan::STATUS_ACTIVE,
            'renewal_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tenantGraph(): array
    {
        $library = Library::factory()->create();
        $otherLibrary = Library::factory()->create();
        $branch = Branch::factory()->create(['library_id' => $library->id]);
        $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);
        $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
        $otherLocation = Location::factory()->create(['library_id' => $otherLibrary->id, 'branch_id' => $otherBranch->id]);
        $book = Book::factory()->create();
        $member = User::factory()->member()->create(['library_id' => $library->id]);
        $staff = User::factory()->staff()->create(['library_id' => $library->id]);
        $otherMember = User::factory()->member()->create(['library_id' => $otherLibrary->id]);

        $copy = BookCopy::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);
        $otherCopy = BookCopy::factory()->create([
            'library_id' => $otherLibrary->id,
            'book_id' => $book->id,
            'branch_id' => $otherBranch->id,
            'location_id' => $otherLocation->id,
            'status' => BookCopy::STATUS_AVAILABLE,
        ]);

        $reservation = Reservation::factory()->create([
            'library_id' => $library->id,
            'book_id' => $book->id,
            'user_id' => $member->id,
            'scope' => Reservation::SCOPE_BRANCH,
            'branch_id' => $branch->id,
            'pickup_branch_id' => $branch->id,
            'assigned_book_copy_id' => $copy->id,
            'status' => Reservation::STATUS_READY,
        ]);

        return compact(
            'library',
            'otherLibrary',
            'branch',
            'otherBranch',
            'location',
            'otherLocation',
            'book',
            'member',
            'staff',
            'otherMember',
            'copy',
            'otherCopy',
            'reservation',
        );
    }
}
