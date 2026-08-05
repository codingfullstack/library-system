<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\Manage\BookCopies\BookCopyForm;
use App\Livewire\Manage\BookCopies\CreateBookCopyPage;
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

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_MAINTENANCE);
    expect($copy->fresh()->condition_status)->toBe(BookCopy::CONDITION_DAMAGED);

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

test('damaged remains a physical condition and is not a lifecycle status', function () {
    expect(BookCopy::conditionLabels())
        ->toHaveKey(BookCopy::CONDITION_DAMAGED)
        ->and(BookCopy::conditionLabels()[BookCopy::CONDITION_DAMAGED])
        ->toBe('Sugadinta')
        ->and(BookCopy::statusLabels())
        ->not->toHaveKey(BookCopy::CONDITION_DAMAGED);
});

test('staff marks physical condition as damaged without changing lifecycle status', function () {
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
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => BookCopy::CONDITION_GOOD,
    ]);

    $this->actingAs($staff)
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::LIFECYCLE_MARK_CONDITION_DAMAGED,
            'reason_notes' => 'Apžiūros metu apgadintas viršelis.',
        ])
        ->assertRedirect(route('book-copies.show', $copy));

    $fresh = $copy->fresh();

    expect($fresh->status)->toBe(BookCopy::STATUS_AVAILABLE)
        ->and($fresh->condition_status)->toBe(BookCopy::CONDITION_DAMAGED);

    $this->assertDatabaseHas('book_copy_status_histories', [
        'book_copy_id' => $copy->id,
        'from_status' => BookCopy::STATUS_AVAILABLE,
        'to_status' => BookCopy::STATUS_AVAILABLE,
        'reason_code' => 'marked_damaged',
    ]);
});

test('edit form can not directly change lifecycle status', function () {
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

    $this->actingAs($admin)
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
        ->assertRedirect(route('book-copies.show', $copy->id));

    $fresh = $copy->fresh();

    expect($fresh->status)->toBe(BookCopy::STATUS_AVAILABLE)
        ->and($fresh->condition_status)->toBe(BookCopy::CONDITION_DAMAGED);
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
    $maintenanceCopy->update(['status' => BookCopy::STATUS_MAINTENANCE]);

    expect(fn () => app(\App\Actions\Loans\BorrowBookCopyAction::class)->handle($staff, $maintenanceCopy->fresh(), [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);

    $withdrawnCopy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
    ]);
    $withdrawnCopy->update(['status' => BookCopy::STATUS_WITHDRAWN]);

    expect(fn () => app(\App\Actions\Loans\BorrowBookCopyAction::class)->handle($staff, $withdrawnCopy->fresh(), [
        'user_id' => $member->id,
        'due_at' => now()->addDays(14)->toDateString(),
        'no_due_date' => false,
        'notes' => null,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
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
        'status' => BookCopy::STATUS_LOANED,
        'condition_status' => 'gera',
        'acquired_at' => now()->toDateString(),
        'notes' => null,
    ]);

    Loan::factory()->create([
        'library_id' => $library->id,
        'book_copy_id' => $copy->id,
        'user_id' => $member->id,
        'status' => 'aktyvi',
        'returned_at' => null,
    ]);

    $response = $this
        ->actingAs($staff)
        ->from(route('book-copies.show', $copy))
        ->patch(route('manage.book-copies.lifecycle.update', $copy), [
            'target_status' => BookCopy::STATUS_LOST,
            'reason_notes' => 'Bandymas pažymėti kaip prarasta.',
        ]);

    $response->assertRedirect(route('book-copies.show', $copy));
    $response->assertSessionHasErrors('target_status');

    expect($copy->fresh()->status)->toBe(BookCopy::STATUS_LOANED);

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



