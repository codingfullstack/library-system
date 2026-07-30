<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Tenancy\TenantIntegrityAuditor;
use Illuminate\Support\Facades\DB;

it('passes when tenant references are internally consistent', function () {
    tenantIntegrityGraph();

    expect(app(TenantIntegrityAuditor::class)->violations())->toBeEmpty();

    $this->artisan('tenants:audit-integrity')
        ->expectsOutput('No tenant integrity violations found.')
        ->assertExitCode(0);
});

it('returns non-zero and does not mutate data when violations exist', function () {
    $graph = tenantIntegrityGraph();

    withTestForeignKeysDisabled(fn () => DB::table('book_copies')
        ->where('id', $graph['copy']->id)
        ->update(['branch_id' => $graph['otherBranch']->id]));

    $this->artisan('tenants:audit-integrity')->assertExitCode(1);

    $violation = app(TenantIntegrityAuditor::class)->violations()->first();
    expect($violation->table)->toBe('book_copies');
    expect($violation->id)->toBe($graph['copy']->id);
    expect($violation->type)->toBe('branch_tenant_mismatch');

    expect(DB::table('book_copies')->where('id', $graph['copy']->id)->value('branch_id'))
        ->toBe($graph['otherBranch']->id);
});

it('detects every tenant integrity violation type', function (string $expectedTable, string $expectedType, Closure $mutate) {
    $graph = tenantIntegrityGraph();

    $id = withTestForeignKeysDisabled(fn () => $mutate($graph));
    $violations = app(TenantIntegrityAuditor::class)->violations();

    expect($violations)->toHaveCount(1);
    expect($violations->first()->table)->toBe($expectedTable);
    expect($violations->first()->id)->toBe($id);
    expect($violations->first()->type)->toBe($expectedType);
})->with([
    'locations branch tenant' => ['locations', 'branch_tenant_mismatch', function (array $graph): int {
        DB::table('locations')->where('id', $graph['location']->id)->update(['branch_id' => $graph['otherBranch']->id]);

        return $graph['location']->id;
    }],
    'book copies branch tenant' => ['book_copies', 'branch_tenant_mismatch', function (array $graph): int {
        DB::table('book_copies')->where('id', $graph['copy']->id)->update(['branch_id' => $graph['otherBranch']->id]);

        return $graph['copy']->id;
    }],
    'book copies location tenant' => ['book_copies', 'location_tenant_mismatch', function (array $graph): int {
        DB::table('book_copies')->where('id', $graph['copy']->id)->update(['location_id' => $graph['otherLocation']->id]);

        return $graph['copy']->id;
    }],
    'memberships branch tenant' => ['library_memberships', 'branch_tenant_mismatch', function (array $graph): int {
        DB::table('library_memberships')->where('id', $graph['staffMembership']->id)->update(['branch_id' => $graph['otherBranch']->id]);

        return $graph['staffMembership']->id;
    }],
    'loans copy tenant' => ['loans', 'book_copy_tenant_mismatch', function (array $graph): int {
        DB::table('loans')->where('id', $graph['loan']->id)->update(['book_copy_id' => $graph['otherCopy']->id]);

        return $graph['loan']->id;
    }],
    'loans borrower membership' => ['loans', 'borrower_membership_missing', function (array $graph): int {
        DB::table('loans')->where('id', $graph['loan']->id)->update(['user_id' => $graph['otherMember']->id]);

        return $graph['loan']->id;
    }],
    'reservations member membership' => ['reservations', 'member_membership_missing', function (array $graph): int {
        DB::table('reservations')->where('id', $graph['reservation']->id)->update(['user_id' => $graph['otherMember']->id]);

        return $graph['reservation']->id;
    }],
    'reservations branch tenant' => ['reservations', 'branch_tenant_mismatch', function (array $graph): int {
        DB::table('reservations')->where('id', $graph['reservation']->id)->update(['branch_id' => $graph['otherBranch']->id]);

        return $graph['reservation']->id;
    }],
    'reservations pickup branch tenant' => ['reservations', 'pickup_branch_tenant_mismatch', function (array $graph): int {
        DB::table('reservations')->where('id', $graph['reservation']->id)->update(['pickup_branch_id' => $graph['otherBranch']->id]);

        return $graph['reservation']->id;
    }],
    'reservations assigned copy tenant' => ['reservations', 'assigned_book_copy_tenant_mismatch', function (array $graph): int {
        DB::table('reservations')->where('id', $graph['reservation']->id)->update(['assigned_book_copy_id' => $graph['otherCopy']->id]);

        return $graph['reservation']->id;
    }],
    'scan logs copy tenant' => ['scan_logs', 'book_copy_tenant_mismatch', function (array $graph): int {
        DB::table('scan_logs')->where('id', $graph['scanLogId'])->update(['book_copy_id' => $graph['otherCopy']->id]);

        return $graph['scanLogId'];
    }],
]);

function tenantIntegrityGraph(): array
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
    $otherStaff = User::factory()->staff()->create(['library_id' => $otherLibrary->id]);
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

    $loan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'issued_by' => $staff->id,
        'received_by' => null,
        'returned_at' => now(),
        'status' => Loan::STATUS_RETURNED,
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

    $scanLogId = DB::table('scan_logs')->insertGetId([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'scan_value' => 'QR-TEST',
        'scan_type' => 'info',
        'result' => 'success',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        'library' => $library,
        'otherLibrary' => $otherLibrary,
        'branch' => $branch,
        'otherBranch' => $otherBranch,
        'location' => $location,
        'otherLocation' => $otherLocation,
        'book' => $book,
        'member' => $member,
        'staff' => $staff,
        'otherMember' => $otherMember,
        'otherStaff' => $otherStaff,
        'staffMembership' => LibraryMembership::query()->where('library_id', $library->id)->where('user_id', $staff->id)->firstOrFail(),
        'copy' => $copy,
        'otherCopy' => $otherCopy,
        'loan' => $loan,
        'reservation' => $reservation,
        'scanLogId' => $scanLogId,
    ];
}
