<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Livewire\Manage\BookCopies\CreateBookCopyPage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('book copy creation page selects a book through livewire drawer', function () {
    $library = Library::factory()->create(['name' => 'Centrine biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $book = Book::factory()->create(['title' => 'Livewire pasirinkta knyga']);

    $this->actingAs($staff)
        ->get(route('manage.book-copies.create'))
        ->assertOk()
        ->assertSee('Pridėti egzempliorių')
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

test('staff can change book copy lifecycle and see status history', function () {
    $library = Library::factory()->create(['name' => 'Centrine biblioteka']);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
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

test('book copy lifecycle can not be changed while copy has active loan', function () {
    $library = Library::factory()->create();
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $member = User::factory()->member()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
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
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $branch = Branch::factory()->create(['library_id' => $library->id]);
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




