<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Actions\Loans\ReturnBookCopyAction;
use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Actions\Reservations\SyncReservationQueueAction;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Reservation;
use App\Queries\Reports\GetDashboardReportDataQuery;
use App\Services\BookCopyBranchTransferService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function seedDashboardScopeFixture(): array
{
    $libraryA = Library::factory()->create(['name' => 'Biblioteka A', 'code' => 'A']);
    $libraryB = Library::factory()->create(['name' => 'Biblioteka B', 'code' => 'B']);
    $branchA1 = Branch::factory()->create(['library_id' => $libraryA->id, 'name' => 'A1 filialas']);
    $branchA2 = Branch::factory()->create(['library_id' => $libraryA->id, 'name' => 'A2 filialas']);
    $branchB1 = Branch::factory()->create(['library_id' => $libraryB->id, 'name' => 'B1 filialas']);

    $bookA1 = Book::factory()->create(['title' => 'A1 knyga']);
    $bookA2 = Book::factory()->create(['title' => 'A2 knyga']);
    $bookB1 = Book::factory()->create(['title' => 'B1 knyga']);

    $memberA = memberInLibrary($libraryA, ['name' => 'A skaitytojas']);
    $memberB = memberInLibrary($libraryB, ['name' => 'B skaitytojas']);

    $a1Copies = BookCopy::factory()->count(2)->create([
        'library_id' => $libraryA->id,
        'branch_id' => $branchA1->id,
        'book_id' => $bookA1->id,
    ]);
    $a2Copies = BookCopy::factory()->count(3)->create([
        'library_id' => $libraryA->id,
        'branch_id' => $branchA2->id,
        'book_id' => $bookA2->id,
    ]);
    $b1Copies = BookCopy::factory()->count(4)->create([
        'library_id' => $libraryB->id,
        'branch_id' => $branchB1->id,
        'book_id' => $bookB1->id,
    ]);

    Loan::factory()->create([
        'library_id' => $libraryA->id,
        'book_copy_id' => $a1Copies->first()->id,
        'user_id' => $memberA->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'borrowed_at' => now()->subDay(),
    ]);
    Loan::factory()->create([
        'library_id' => $libraryA->id,
        'book_copy_id' => $a2Copies->first()->id,
        'user_id' => $memberA->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'borrowed_at' => now()->subDays(10),
    ]);
    Loan::factory()->create([
        'library_id' => $libraryB->id,
        'book_copy_id' => $b1Copies->first()->id,
        'user_id' => $memberB->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
        'borrowed_at' => now()->subDay(),
    ]);

    Reservation::factory()->create([
        'library_id' => $libraryA->id,
        'book_id' => $bookA1->id,
        'user_id' => $memberA->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA1->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDay(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryA->id,
        'book_id' => $bookA2->id,
        'user_id' => $memberA->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchA2->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDay(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryA->id,
        'book_id' => $bookA1->id,
        'user_id' => $memberA->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDay(),
    ]);
    Reservation::factory()->create([
        'library_id' => $libraryB->id,
        'book_id' => $bookB1->id,
        'user_id' => $memberB->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branchB1->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now()->subDay(),
    ]);

    return compact('libraryA', 'libraryB', 'branchA1', 'branchA2', 'branchB1');
}

it('centralizes admin and staff dashboard scope across report blocks', function () {
    $fixture = seedDashboardScopeFixture();
    $query = app(GetDashboardReportDataQuery::class);
    $admin = adminInLibrary($fixture['libraryA']);

    $libraryReport = $query->handle($admin);
    expect($libraryReport['summary']['book_copies_count'])->toBe(5)
        ->and($libraryReport['summary']['active_loans_count'])->toBe(2)
        ->and($libraryReport['summary']['active_reservations_count'])->toBe(3)
        ->and($libraryReport['popularBooks']->pluck('title')->all())->toContain('A1 knyga', 'A2 knyga')
        ->and($libraryReport['popularBooks']->pluck('title')->all())->not->toContain('B1 knyga');

    $a1Report = $query->handle($admin, ['branch_id' => $fixture['branchA1']->id]);
    expect($a1Report['summary']['book_copies_count'])->toBe(2)
        ->and($a1Report['summary']['active_loans_count'])->toBe(1)
        ->and($a1Report['summary']['active_reservations_count'])->toBe(1)
        ->and($a1Report['summary']['active_members_count'])->toBeNull()
        ->and($a1Report['popularBooks']->pluck('title')->all())->toContain('A1 knyga')
        ->and($a1Report['popularBooks']->pluck('title')->all())->not->toContain('A2 knyga', 'B1 knyga')
        ->and($a1Report['copiesByBranch'])->toHaveCount(1);

    $a2Report = $query->handle($admin, ['branch_id' => $fixture['branchA2']->id]);
    expect($a2Report['summary']['book_copies_count'])->toBe(3)
        ->and($a2Report['summary']['active_loans_count'])->toBe(1)
        ->and($a2Report['summary']['active_reservations_count'])->toBe(1)
        ->and($a2Report['popularBooks']->pluck('title')->all())->toContain('A2 knyga');

    $staffReport = $query->handle(staffInBranch($fixture['libraryA'], $fixture['branchA1']), ['branch_id' => $fixture['branchA2']->id]);
    expect($staffReport['dashboardScope']->branchId)->toBe($fixture['branchA1']->id)
        ->and($staffReport['dashboardScope']->canSelectBranch)->toBeFalse()
        ->and($staffReport['summary']['book_copies_count'])->toBe(2);
});

it('rejects foreign admin branches and staff without valid branch', function () {
    $fixture = seedDashboardScopeFixture();
    $query = app(GetDashboardReportDataQuery::class);

    expect(fn () => $query->handle(adminInLibrary($fixture['libraryA']), ['branch_id' => $fixture['branchB1']->id]))
        ->toThrow(NotFoundHttpException::class);

    expect(fn () => $query->handle(staffWithoutBranch($fixture['libraryA'])))
        ->toThrow(HttpException::class);
});

it('rejects malformed branch ids without falling back to the whole library', function ($branchId) {
    $fixture = seedDashboardScopeFixture();
    $query = app(GetDashboardReportDataQuery::class);
    $admin = adminInLibrary($fixture['libraryA']);

    expect(fn () => $query->handle($admin, ['branch_id' => $branchId]))
        ->toThrow(NotFoundHttpException::class);
})->with([0, -5, 'tekstas', '1.5', 999999]);

it('rejects array branch ids without falling back to the whole library', function () {
    $fixture = seedDashboardScopeFixture();
    $query = app(GetDashboardReportDataQuery::class);
    $admin = adminInLibrary($fixture['libraryA']);

    expect(fn () => $query->handle($admin, ['branch_id' => [$fixture['branchA1']->id]]))
        ->toThrow(NotFoundHttpException::class);
});

it('keeps forged staff scope fixed across query api export and livewire', function () {
    $fixture = seedDashboardScopeFixture();
    $staff = staffInBranch($fixture['libraryA'], $fixture['branchA1']);
    $query = app(GetDashboardReportDataQuery::class);

    $report = $query->handle($staff, ['branch_id' => $fixture['branchA2']->id]);
    expect($report['dashboardScope']->branchId)->toBe($fixture['branchA1']->id)
        ->and($report['summary']['book_copies_count'])->toBe(2);

    $this->actingAs($staff)
        ->getJson('/api/auth/dashboard/summary?branch_id='.$fixture['branchA2']->id)
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', 2)
        ->assertJsonPath('summary.active_loans_count', 1);

    $this->actingAs($staff)
        ->get(route('dashboard.export', ['format' => 'csv', 'branch_id' => $fixture['branchA2']->id]))
        ->assertOk()
        ->assertSee('A1 filialas')
        ->assertDontSee('A2 knyga');

    Livewire::actingAs($staff)
        ->test('dashboard.overview', ['filters' => ['branch_id' => $fixture['branchA2']->id]])
        ->assertSee('Filialas: A1 filialas')
        ->assertDontSee('Visa biblioteka');
});

it('applies a single state sensitive reservation branch for reporting', function () {
    $fixture = seedDashboardScopeFixture();
    $query = app(GetDashboardReportDataQuery::class);
    $admin = adminInLibrary($fixture['libraryA']);
    $member = memberInLibrary($fixture['libraryA'], ['name' => 'Rezervaciju narys']);
    $book = Book::factory()->create(['title' => 'Busenu knyga']);
    $copyA1 = BookCopy::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'branch_id' => $fixture['branchA1']->id,
        'book_id' => $book->id,
    ]);

    Reservation::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => null,
        'report_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);

    Reservation::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA1']->id,
        'report_branch_id' => $fixture['branchA1']->id,
        'status' => Reservation::STATUS_WAITING,
        'reserved_at' => now(),
    ]);

    Reservation::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA1']->id,
        'pickup_branch_id' => $fixture['branchA2']->id,
        'report_branch_id' => $fixture['branchA2']->id,
        'assigned_book_copy_id' => $copyA1->id,
        'status' => Reservation::STATUS_READY,
        'reserved_at' => now(),
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    Reservation::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchA1']->id,
        'report_branch_id' => $fixture['branchA1']->id,
        'status' => Reservation::STATUS_CANCELLED,
        'reserved_at' => now(),
        'cancelled_at' => now(),
    ]);

    Reservation::factory()->create([
        'library_id' => $fixture['libraryA']->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => null,
        'report_branch_id' => $fixture['branchA2']->id,
        'assigned_book_copy_id' => null,
        'status' => Reservation::STATUS_EXPIRED,
        'reserved_at' => now(),
        'ready_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    $a1 = $query->handle($admin, ['branch_id' => $fixture['branchA1']->id]);
    $a2 = $query->handle($admin, ['branch_id' => $fixture['branchA2']->id]);

    expect($a1['summary']['reservations_count'])->toBe(3)
        ->and($a1['summary']['active_reservations_count'])->toBe(2)
        ->and($a2['summary']['reservations_count'])->toBe(3)
        ->and($a2['summary']['active_reservations_count'])->toBe(2);
});

it('uses immutable loan branch snapshots after a copy moves', function () {
    $library = Library::factory()->create(['name' => 'Istorijos biblioteka']);
    $branchA1 = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Istorinis A1']);
    $branchA2 = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Dabartinis A2']);
    $book = Book::factory()->create(['title' => 'Snapshot knyga']);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branchA1->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $member = memberInLibrary($library);
    $staff = staffInBranch($library, $branchA1);

    $loan = app(BorrowBookCopyAction::class)->handle($staff, $copy, [
        'user_id' => $member->id,
        'issued_branch_id' => $branchA2->id,
    ])['loan'];

    expect($loan->fresh()->issued_branch_id)->toBe($branchA1->id);

    $copy->update(['branch_id' => $branchA2->id]);

    $query = app(GetDashboardReportDataQuery::class);
    $admin = adminInLibrary($library);
    $a1 = $query->handle($admin, ['branch_id' => $branchA1->id]);
    $a2 = $query->handle($admin, ['branch_id' => $branchA2->id]);

    expect($a1['summary']['active_loans_count'])->toBe(1)
        ->and($a1['popularBooks']->pluck('title')->all())->toContain('Snapshot knyga')
        ->and($a2['summary']['active_loans_count'])->toBe(0);

    app(ReturnBookCopyAction::class)->handle(adminInLibrary($library), $copy->fresh());
    $returnedLoan = $loan->fresh();
    expect($returnedLoan->issued_branch_id)->toBe($branchA1->id)
        ->and($returnedLoan->returned_branch_id)->toBe($branchA2->id);

    $legacyLoan = Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_RETURNED,
        'returned_at' => now(),
    ]);
    $legacyLoan->forceFill([
        'issued_branch_id' => null,
        'returned_branch_id' => null,
    ])->save();

    $a2AfterLegacy = $query->handle($admin, ['branch_id' => $branchA2->id]);
    expect($a2AfterLegacy['summary']['loans_count'])->toBe(0)
        ->and($a2AfterLegacy['summary']['returned_loans_count'])->toBe(1);
});

it('sets reservation report branch in authoritative domain transitions', function () {
    $library = Library::factory()->create(['name' => 'Rezervaciju invariantu biblioteka']);
    $branch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Invariantinis filialas']);
    $staff = staffInBranch($library, $branch);
    $member = memberInLibrary($library, ['name' => 'Invariantinis narys']);

    $waitingBranchBook = Book::factory()->create(['title' => 'Laukianti filialo knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'book_id' => $waitingBranchBook->id,
        'status' => BookCopy::STATUS_MAINTENANCE,
        'lifecycle_status' => BookCopy::STATUS_MAINTENANCE,
    ]);

    $waitingBranch = app(CreateReservationAction::class)->handle($member, [
        'book_id' => $waitingBranchBook->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branch->id,
    ]);
    expect($waitingBranch->fresh()->report_branch_id)->toBe($branch->id);

    $waitingLibraryBook = Book::factory()->create(['title' => 'Laukianti bibliotekos knyga']);
    BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'book_id' => $waitingLibraryBook->id,
        'status' => BookCopy::STATUS_MAINTENANCE,
        'lifecycle_status' => BookCopy::STATUS_MAINTENANCE,
    ]);
    $waitingLibrary = app(CreateReservationAction::class)->handle($member, [
        'book_id' => $waitingLibraryBook->id,
        'scope' => Reservation::SCOPE_LIBRARY,
    ]);
    expect($waitingLibrary->fresh()->report_branch_id)->toBeNull();

    $readyBook = Book::factory()->create(['title' => 'Paruosiama knyga']);
    $readyCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'book_id' => $readyBook->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $readyReservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $readyBook->id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => null,
        'report_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
    ]);

    app(SyncReservationQueueAction::class)->handle($library->id, $readyBook->id);
    $readyReservation = $readyReservation->fresh();
    expect($readyReservation->status)->toBe(Reservation::STATUS_READY)
        ->and($readyReservation->assigned_book_copy_id)->toBe($readyCopy->id)
        ->and($readyReservation->pickup_branch_id)->toBe($branch->id)
        ->and($readyReservation->report_branch_id)->toBe($branch->id);

    app(BorrowBookCopyAction::class)->handle($staff, $readyCopy, ['user_id' => $readyReservation->user_id]);
    expect($readyReservation->fresh()->report_branch_id)->toBe($branch->id);

    $cancelWaiting = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => Book::factory()->create()->id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $branch->id,
        'report_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
    ]);
    app(CancelReservationAction::class)->handle($staff, $cancelWaiting, 'Test');
    expect($cancelWaiting->fresh()->report_branch_id)->toBe($branch->id);

    $cancelReadyCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'book_id' => Book::factory()->create()->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $cancelReady = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $cancelReadyCopy->book_id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branch->id,
        'report_branch_id' => $branch->id,
        'assigned_book_copy_id' => $cancelReadyCopy->id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);
    app(CancelReservationAction::class)->handle($staff, $cancelReady, 'Test');
    expect($cancelReady->fresh()->report_branch_id)->toBe($branch->id);

    $expiredReadyCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
        'book_id' => Book::factory()->create()->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $expiredReady = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $expiredReadyCopy->book_id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branch->id,
        'report_branch_id' => $branch->id,
        'assigned_book_copy_id' => $expiredReadyCopy->id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);
    $this->artisan('reservations:expire')->assertExitCode(0);
    expect($expiredReady->fresh()->report_branch_id)->toBe($branch->id);
});

it('rejects mismatching branches before a reservation is transitioned to ready', function () {
    $library = Library::factory()->create(['name' => 'Ready invariant library']);
    $branchA = Branch::factory()->create(['library_id' => $library->id, 'name' => 'A filialas']);
    $branchB = Branch::factory()->create(['library_id' => $library->id, 'name' => 'B filialas']);
    $book = Book::factory()->create(['title' => 'Ready invariant book']);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branchA->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => memberInLibrary($library)->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => null,
        'report_branch_id' => null,
        'status' => Reservation::STATUS_WAITING,
    ]);

    $guard = new ReflectionMethod(SyncReservationQueueAction::class, 'assertReadyAssignmentInvariant');

    expect(fn () => $guard->invoke(app(SyncReservationQueueAction::class), $reservation, $copy, [
        'status' => Reservation::STATUS_READY,
        'assigned_book_copy_id' => $copy->id,
        'pickup_branch_id' => $branchB->id,
        'report_branch_id' => $branchB->id,
    ]))->toThrow(ValidationException::class);

    expect($reservation->fresh()->status)->toBe(Reservation::STATUS_WAITING)
        ->and($reservation->fresh()->assigned_book_copy_id)->toBeNull()
        ->and($reservation->fresh()->pickup_branch_id)->toBeNull()
        ->and($reservation->fresh()->report_branch_id)->toBeNull();
});

it('rejects ready reservation branch mismatches and ready copy transfers', function () {
    $library = Library::factory()->create(['name' => 'Nesutapimu biblioteka']);
    $branchA = Branch::factory()->create(['library_id' => $library->id, 'name' => 'A filialas']);
    $branchB = Branch::factory()->create(['library_id' => $library->id, 'name' => 'B filialas']);
    $book = Book::factory()->create(['title' => 'Nesutampanti knyga']);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branchA->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $member = memberInLibrary($library);
    $staff = staffInBranch($library, $branchA);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branchB->id,
        'report_branch_id' => $branchB->id,
        'assigned_book_copy_id' => $copy->id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $copy, ['user_id' => $member->id]))
        ->toThrow(ValidationException::class);
    expect(Loan::query()->where('book_copy_id', $copy->id)->exists())->toBeFalse();

    Reservation::query()->delete();
    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'scope' => Reservation::SCOPE_LIBRARY,
        'branch_id' => null,
        'pickup_branch_id' => $branchA->id,
        'report_branch_id' => $branchA->id,
        'assigned_book_copy_id' => $copy->id,
        'status' => Reservation::STATUS_READY,
        'ready_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    expect(fn () => app(BookCopyBranchTransferService::class)->resolveBranchId(
        adminInLibrary($library),
        $library->id,
        $branchB->id,
        $copy
    ))->toThrow(ValidationException::class);
});

it('returns complete numeric timeline buckets and empty chart state per branch scope', function () {
    $library = Library::factory()->create(['name' => 'Timeline biblioteka']);
    $branchA = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Veiklus filialas']);
    $branchB = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Tuscias filialas']);
    $book = Book::factory()->create(['title' => 'Timeline knyga']);
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branchA->id,
        'book_id' => $book->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
    ]);
    $member = memberInLibrary($library);
    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_RETURNED,
        'borrowed_at' => now()->subDays(3),
        'returned_at' => now()->subDays(2),
        'issued_branch_id' => $branchA->id,
        'returned_branch_id' => $branchA->id,
    ]);

    $admin = adminInLibrary($library);
    $filters = [
        'branch_id' => $branchA->id,
        'date_from' => now()->subDays(6)->startOfDay()->toImmutable(),
        'date_to' => now()->endOfDay()->toImmutable(),
        'period_label' => 'Test intervalas',
    ];
    $timeline = app(GetDashboardReportDataQuery::class)->handle($admin, $filters)['activityTimeline'];

    expect($timeline)->toHaveCount(7)
        ->and(collect($timeline)->pluck('label'))->toHaveCount(7)
        ->and(collect($timeline)->pluck('issued_loans_count')->all())->not->toContain(null)
        ->and(collect($timeline)->pluck('returned_loans_count')->all())->not->toContain(null)
        ->and(collect($timeline)->pluck('reservations_count')->all())->not->toContain(null)
        ->and(collect($timeline)->sum('issued_loans_count'))->toBe(1)
        ->and(collect($timeline)->sum('returned_loans_count'))->toBe(1);

    $emptyTimeline = app(GetDashboardReportDataQuery::class)->handle($admin, array_merge($filters, ['branch_id' => $branchB->id]))['activityTimeline'];
    expect($emptyTimeline)->toHaveCount(7)
        ->and(collect($emptyTimeline)->sum('issued_loans_count'))->toBe(0)
        ->and(collect($emptyTimeline)->sum('returned_loans_count'))->toBe(0)
        ->and(collect($emptyTimeline)->sum('reservations_count'))->toBe(0);

    Livewire::actingAs($admin)
        ->test('dashboard.overview', ['filters' => ['branch_id' => $branchA->id]])
        ->set('period', '7_days')
        ->assertDontSee('Pasirinktu laikotarpiu šiame filiale nebuvo išdavimų, grąžinimų ar rezervacijų.')
        ->set('branchId', (string) $branchB->id)
        ->assertSee('Pasirinktu laikotarpiu šiame filiale nebuvo išdavimų, grąžinimų ar rezervacijų.');
});

it('exports the same validated branch scope as the dashboard screen', function () {
    $fixture = seedDashboardScopeFixture();
    $admin = adminInLibrary($fixture['libraryA']);
    $querySummary = app(GetDashboardReportDataQuery::class)
        ->handle($admin, ['branch_id' => $fixture['branchA1']->id])['summary'];

    $this->actingAs($admin)
        ->getJson('/api/auth/dashboard/summary?branch_id='.$fixture['branchA1']->id)
        ->assertOk()
        ->assertJsonPath('summary.book_copies_count', $querySummary['book_copies_count'])
        ->assertJsonPath('summary.active_loans_count', $querySummary['active_loans_count'])
        ->assertJsonPath('summary.active_reservations_count', $querySummary['active_reservations_count']);

    $response = $this->actingAs($admin)->get(route('dashboard.export', [
        'format' => 'csv',
        'branch_id' => $fixture['branchA1']->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('a-a1-filialas');
    $csvRows = array_map(
        fn (string $line) => str_getcsv($line),
        preg_split('/\r\n|\r|\n/', trim($response->getContent()))
    );
    expect((int) $csvRows[3][1])->toBe($querySummary['book_copies_count'])
        ->and((int) $csvRows[5][1])->toBe($querySummary['active_loans_count'])
        ->and((int) $csvRows[7][1])->toBe($querySummary['active_reservations_count']);
    $response->assertSee('A1 filialas');
    $response->assertSee('A1 knyga');
    $response->assertDontSee('A2 knyga');
    $response->assertDontSee('B1 knyga');
    $response->assertSee('Netaikoma filialo apimciai');
});
