<?php

use App\Actions\Loans\BorrowBookCopyAction;
use App\Livewire\Manage\BookCopies\BookCopyForm;
use App\Livewire\Manage\BookCopies\CreateBookCopyPage;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('book copy creation page selects a book through livewire drawer', function () {
    $library = Library::factory()->create(['name' => 'Centrine biblioteka']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $book = Book::factory()->create(['title' => 'Livewire pasirinkta knyga']);

    $this->actingAs($staff)
        ->get(route('manage.book-copies.create'))
        ->assertOk()
        ->assertSee('Pridėti kopiją')
        ->assertSee('Bendras katalogas')
        ->assertSee('wire:click="selectBook', false);

    Livewire::actingAs($staff)
        ->test(CreateBookCopyPage::class)
        ->call('selectBook', $book->id)
        ->assertSet('selectedBookId', $book->id)
        ->assertSee('Nauja kopija bus pridėta prie pasirinktos knygos.')
        ->assertSee('Livewire pasirinkta knyga')
        ->call('closeDrawer')
        ->assertSet('selectedBookId', null);
});

test('staff creates book copy only in assigned branch', function () {
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Darbuotojo filialas']);
    $otherBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas filialas']);
    $ownLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $ownBranch->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $staff->libraryMemberships()->where('library_id', $library->id)->update(['branch_id' => $ownBranch->id]);
    $book = Book::factory()->create(['title' => 'Filialo kopijos knyga']);

    Livewire::actingAs($staff)
        ->test(BookCopyForm::class, ['selectedBook' => $book])
        ->assertSet('branchId', $ownBranch->id)
        ->assertSee('Darbuotojo filialas')
        ->assertDontSee('Kitas filialas')
        ->set('branchId', $otherBranch->id)
        ->assertSet('branchId', $ownBranch->id)
        ->set('locationId', $ownLocation->id)
        ->set('inventoryCode', 'STAFF-BRANCH-001')
        ->call('save')
        ->assertRedirect();

    $this->assertDatabaseHas('book_copies', [
        'inventory_code' => 'STAFF-BRANCH-001',
        'branch_id' => $ownBranch->id,
    ]);

    $this->assertDatabaseMissing('book_copies', [
        'inventory_code' => 'STAFF-BRANCH-001',
        'branch_id' => $otherBranch->id,
    ]);
});

test('staff can change book copy lifecycle and see status history', function () {
    $library = Library::factory()->create(['name' => 'Centrine biblioteka']);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create(['title' => 'Gyvenimo ciklo knyga']);

    $copy = BookCopy::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-LIFE-001',
        'qr_code' => 'QR-LIFE-001',
        'barcode' => '12345678903',
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => 'gera',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    $response = $this
        ->actingAs($staff)
        ->from(route('book-copies.show', $copy))
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::STATUS_MAINTENANCE,
            'reason_notes' => 'Siunčiama tvarkyti del suluzušio virselio.',
        ]);

    $response->assertRedirect(route('book-copies.show', $copy));
    $response->assertSessionHas('success');

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
    expect($copy->fresh()->lifecycle_status)->toBe(BookCopy::STATUS_MAINTENANCE);
    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_GOOD);

    $this->assertDatabaseHas('book_copy_status_histories', [
        'book_copy_id' => $copy->id,
        'changed_by' => $staff->id,
        'from_status' => BookCopy::STATUS_AVAILABLE,
        'to_status' => BookCopy::STATUS_MAINTENANCE,
        'reason_code' => 'sent_to_maintenance',
    ]);

    $this->actingAs($staff)
        ->get(route('book-copies.show', $copy))
        ->assertOk()
        ->assertSee('Būsenos istorija')
        ->assertSee('Išsiųstas tvarkymui')
        ->assertSee('Siunčiama tvarkyti del suluzušio virselio.');
});

test('physical condition has only the canonical three values and damaged is not lifecycle', function () {
    expect(BookCopy::conditionLabels())
        ->toBe([
            BookCopy::CONDITION_NEW => 'Nauja',
            BookCopy::CONDITION_GOOD => 'Gera',
            BookCopy::CONDITION_WORN => 'Padėvėta',
        ])
        ->and(BookCopy::statusLabels())
        ->toHaveKeys([
            BookCopy::STATUS_PREPARING,
            BookCopy::STATUS_IN_CIRCULATION,
            BookCopy::STATUS_MAINTENANCE,
            BookCopy::STATUS_LOST,
            BookCopy::STATUS_WITHDRAWN,
        ])
        ->and(BookCopy::statusLabels())
        ->not->toHaveKey(BookCopy::LEGACY_STATUS_DAMAGED);
});

test('general edit form does not offer damaged as selectable physical condition', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    Livewire::actingAs($admin)
        ->test(BookCopyForm::class, ['bookCopy' => $copy])
        ->assertSee(BookCopy::conditionLabels()[BookCopy::CONDITION_NEW])
        ->assertSee(BookCopy::conditionLabels()[BookCopy::CONDITION_GOOD])
        ->assertSee(BookCopy::conditionLabels()[BookCopy::CONDITION_WORN])
        ->assertDontSee('Sugadinta')
        ->assertDontSee('Tvarkoma')
        ->assertDontSee('Prarasta')
        ->assertDontSee('Nurašyta');
});

test('copy page uses lifecycle status for lifecycle actions when operational status is legacy available', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create(['title' => 'Legacy laisva kopija']);

    DB::statement('PRAGMA ignore_check_constraints = ON');

    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => 'laisva',
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_NEW,
    ]);

    DB::statement('PRAGMA ignore_check_constraints = OFF');

    $this->actingAs($staff)
        ->get(route('book-copies.show', $copy))
        ->assertOk()
        ->assertSee('Būsena')
        ->assertSee('Laisva')
        ->assertSee('Fizinė būklė')
        ->assertSee('Nauja')
        ->assertSee('Gyvavimo ciklas')
        ->assertSee('Aktyvi')
        ->assertSee('Perduoti tvarkyti')
        ->assertSee('Pažymėti kaip prarastą')
        ->assertSee('Nurašyti')
        ->assertDontSee('Šiam statusui papildomu gyvenimo ciklo veiksmų nebera.');
});

test('withdrawn copy page is the normal no lifecycle action state', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_WITHDRAWN,
        'lifecycle_status' => BookCopy::STATUS_WITHDRAWN,
        'condition_status' => BookCopy::CONDITION_WORN,
    ]);

    $this->actingAs($staff)
        ->get(route('book-copies.show', $copy))
        ->assertOk()
        ->assertSee('Gyvavimo ciklas')
        ->assertSee('Nurašyta')
        ->assertSee('Šiam statusui papildomu gyvenimo ciklo veiksmų nebera.')
        ->assertDontSee('Perduoti tvarkyti')
        ->assertDontSee('Pažymėti kaip prarastą');
});

test('lifecycle transition to maintenance preserves physical condition', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'lifecycle_status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_WORN,
    ]);

    $this->actingAs($staff)
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::STATUS_MAINTENANCE,
            'reason_notes' => 'Reikia sutvirtinti viršelį.',
        ])
        ->assertRedirect(route('book-copies.show', $copy));

    $fresh = $copy->fresh();

    expect($fresh->status)->toBe(BookCopy::STATUS_IN_CIRCULATION)
        ->and($fresh->lifecycle_status)->toBe(BookCopy::STATUS_MAINTENANCE)
        ->and($fresh->condition_status)->toBe(BookCopy::CONDITION_WORN);

    $this->assertDatabaseHas('book_copy_status_histories', [
        'book_copy_id' => $copy->id,
        'from_status' => BookCopy::STATUS_IN_CIRCULATION,
        'to_status' => BookCopy::STATUS_MAINTENANCE,
        'reason_code' => 'sent_to_maintenance',
    ]);
});

test('lifecycle action requires reason before moving copy to maintenance', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    $this->actingAs($staff)
        ->from(route('book-copies.show', $copy))
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::STATUS_MAINTENANCE,
            'reason_notes' => '',
        ])
        ->assertRedirect(route('book-copies.show', $copy))
        ->assertSessionHasErrors('reason_notes');

    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_GOOD);

    $this->assertDatabaseMissing('book_copy_status_histories', [
        'book_copy_id' => $copy->id,
        'reason_code' => 'sent_to_maintenance',
    ]);
});

test('general http update can not directly mark physical condition as damaged', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);
    $historyCount = $copy->statusHistories()->count();
    $auditCount = $copy->auditLogs()->count();

    $this->actingAs($admin)
        ->from(route('manage.book-copies.edit', $copy))
        ->put(route('manage.book-copies.update', $copy), [
            'library_id' => $library->id,
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'location_id' => $location->id,
            'inventory_code' => $copy->inventory_code,
            'barcode' => $copy->barcode,
            'status' => BookCopy::STATUS_WITHDRAWN,
            'condition_status' => BookCopy::CONDITION_DAMAGED,
            'acquired_at' => $copy->acquired_at?->format('Y-m-d'),
            'notes' => 'Atnaujinta tik fizinė būklė.',
        ])
        ->assertRedirect(route('manage.book-copies.edit', $copy))
        ->assertSessionHasErrors([
            'condition_status' => BookCopy::damagedConditionGeneralEditMessage(),
        ]);

    $fresh = $copy->fresh();

    expect($fresh->status)->toBe(BookCopy::STATUS_AVAILABLE)
        ->and($fresh->condition_status)->toBe(BookCopy::CONDITION_GOOD)
        ->and($copy->statusHistories()->count())->toBe($historyCount)
        ->and($copy->auditLogs()->count())->toBe($auditCount);
});

test('general livewire save can not directly mark physical condition as damaged', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);
    $historyCount = $copy->statusHistories()->count();
    $auditCount = $copy->auditLogs()->count();

    Livewire::actingAs($admin)
        ->test(BookCopyForm::class, ['bookCopy' => $copy])
        ->set('conditionStatus', BookCopy::CONDITION_DAMAGED)
        ->set('notes', 'Bandymas apeiti per Livewire.')
        ->call('save')
        ->assertHasErrors(['conditionStatus' => 'in']);

    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_GOOD)
        ->and($copy->fresh()->notes)->not->toBe('Bandymas apeiti per Livewire.')
        ->and($copy->statusHistories()->count())->toBe($historyCount)
        ->and($copy->auditLogs()->count())->toBe($auditCount);
});

test('general edit still allows ordinary physical condition changes', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    Livewire::actingAs($admin)
        ->test(BookCopyForm::class, ['bookCopy' => $copy])
        ->set('conditionStatus', BookCopy::CONDITION_WORN)
        ->set('notes', 'Pakeista bendroje formoje.')
        ->call('save')
        ->assertRedirect();

    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_WORN)
        ->and($copy->fresh()->notes)->toBe('Pakeista bendroje formoje.');
});

test('general livewire edit does not block ordinary changes because of legacy copy status', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    DB::statement('PRAGMA ignore_check_constraints = ON');

    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => 'laisva',
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    DB::statement('PRAGMA ignore_check_constraints = OFF');

    Livewire::actingAs($admin)
        ->test(BookCopyForm::class, ['bookCopy' => $copy])
        ->set('conditionStatus', BookCopy::CONDITION_WORN)
        ->set('notes', 'Pakeista nepaisant legacy statuso.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_WORN)
        ->and($copy->fresh()->notes)->toBe('Pakeista nepaisant legacy statuso.')
        ->and($copy->fresh()->status)->toBe('laisva');
});

test('maintenance and withdrawn copies can not be borrowed', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $maintenanceCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);
    $maintenanceCopy->update([
        'status' => BookCopy::STATUS_MAINTENANCE,
        'lifecycle_status' => BookCopy::STATUS_MAINTENANCE,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $maintenanceCopy->fresh(), [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(ValidationException::class);

    $withdrawnCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);
    $withdrawnCopy->update([
        'status' => BookCopy::STATUS_WITHDRAWN,
        'lifecycle_status' => BookCopy::STATUS_WITHDRAWN,
    ]);

    expect(fn () => app(BorrowBookCopyAction::class)->handle($staff, $withdrawnCopy->fresh(), [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(ValidationException::class);
});

test('book copy lifecycle can not be changed while copy has active loan', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();

    $copy = BookCopy::create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'inventory_code' => 'INV-LIFE-002',
        'qr_code' => 'QR-LIFE-002',
        'barcode' => '12345678904',
        'status' => BookCopy::STATUS_IN_CIRCULATION,
        'condition_status' => 'gera',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);

    expect($copy->activeLoan()->exists())->toBeTrue();

    $response = $this
        ->actingAs($staff)
        ->from(route('book-copies.show', $copy))
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::STATUS_LOST,
            'reason_notes' => 'Bandymas pažymėti kaip prarasta.',
        ]);

    $response->assertRedirect(route('book-copies.show', $copy));
    $response->assertSessionHas('error');

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_IN_CIRCULATION);

    $this->assertDatabaseMissing('book_copy_status_histories', [
        'book_copy_id' => $copy->id,
        'to_status' => BookCopy::STATUS_LOST,
    ]);
});

test('staff can delete book copy from own library', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('manage.book-copies.destroy', $copy))
        ->assertRedirect(route('books.index'));

    $this->assertDatabaseMissing('book_copies', [
        'id' => $copy->id,
    ]);
});

test('staff can not delete book copy with reservation history', function () {
    $library = Library::factory()->create();
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = staffInBranch($library, $branch);
    $location = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);

    Reservation::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'user_id' => $member->id,
        'branch_id' => $branch->id,
        'pickup_branch_id' => $branch->id,
        'assigned_book_copy_id' => $copy->id,
        'status' => Reservation::STATUS_FULFILLED,
        'ready_at' => now()->subDays(3),
        'expires_at' => now()->addDays(11),
        'fulfilled_at' => now()->subDay(),
    ]);

    $this->actingAs($staff)
        ->from(route('book-copies.show', $copy))
        ->delete(route('manage.book-copies.destroy', $copy))
        ->assertRedirect(route('book-copies.show', $copy))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('book_copies', [
        'id' => $copy->id,
    ]);
});

test('staff can not delete book copy from another library', function () {
    $staffLibrary = Library::factory()->create();
    $copyLibrary = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $staffLibrary->id]);
    $branch = Branch::factory()->create(['library_id' => $copyLibrary->id]);
    $location = Location::factory()->create(['library_id' => $copyLibrary->id, 'branch_id' => $branch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $copyLibrary->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('manage.book-copies.destroy', $copy))
        ->assertForbidden();

    $this->assertDatabaseHas('book_copies', [
        'id' => $copy->id,
    ]);
});
