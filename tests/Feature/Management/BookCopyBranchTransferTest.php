<?php

use App\Livewire\Manage\BookCopies\BookCopyForm;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function transferPayload(BookCopy $copy, Branch $branch, ?Location $location = null, array $overrides = []): array
{
    return array_merge([
        'library_id' => $copy->library_id,
        'book_id' => $copy->book_id,
        'branch_id' => $branch->id,
        'location_id' => $location?->id,
        'inventory_code' => $copy->inventory_code,
        'barcode' => $copy->barcode,
        'status' => $copy->status,
        'condition_status' => $copy->condition_status,
        'acquired_at' => $copy->acquired_at?->format('Y-m-d'),
        'notes' => $copy->notes,
    ], $overrides);
}

function transferFixture(): array
{
    $library = Library::factory()->create();
    $ownBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Savas filialas']);
    $targetBranch = Branch::factory()->create(['library_id' => $library->id, 'name' => 'Kitas filialas']);
    $ownLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $ownBranch->id]);
    $targetLocation = Location::factory()->create(['library_id' => $library->id, 'branch_id' => $targetBranch->id]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $ownBranch->id,
        'location_id' => $ownLocation->id,
        'status' => BookCopy::STATUS_AVAILABLE,
        'condition_status' => 'gera',
    ]);

    return compact('library', 'ownBranch', 'targetBranch', 'ownLocation', 'targetLocation', 'book', 'copy');
}

test('staff can not change book copy branch through controller update', function () {
    $fixture = transferFixture();
    $staff = User::factory()->staff()->create(['library_id' => $fixture['library']->id]);
    $staff->libraryMemberships()->where('library_id', $fixture['library']->id)->update([
        'branch_id' => $fixture['ownBranch']->id,
    ]);

    $this->actingAs($staff)
        ->from(route('manage.book-copies.edit', $fixture['copy']))
        ->put(route('manage.book-copies.update', $fixture['copy']), transferPayload(
            $fixture['copy'],
            $fixture['targetBranch'],
            $fixture['targetLocation']
        ))
        ->assertRedirect(route('manage.book-copies.edit', $fixture['copy']))
        ->assertSessionHasErrors('branch_id');

    expect($fixture['copy']->fresh()->branch_id)->toBe($fixture['ownBranch']->id);
});

test('staff can not bypass branch transfer rules through api lifecycle endpoint', function () {
    $fixture = transferFixture();
    $staff = User::factory()->staff()->create(['library_id' => $fixture['library']->id]);
    $staff->libraryMemberships()->where('library_id', $fixture['library']->id)->update([
        'branch_id' => $fixture['ownBranch']->id,
    ]);

    $this->actingAs($staff)
        ->patchJson("/api/auth/book-copies/{$fixture['copy']->id}/lifecycle", [
            'target_status' => BookCopy::STATUS_MAINTENANCE,
            'reason_notes' => 'API bandymas.',
            'branch_id' => $fixture['targetBranch']->id,
        ])
        ->assertOk();

    $fresh = $fixture['copy']->fresh();

    expect($fresh->branch_id)->toBe($fixture['ownBranch']->id)
        ->and($fresh->status)->toBe(BookCopy::STATUS_MAINTENANCE);
});

test('staff can not bypass branch transfer rules through livewire form', function () {
    $fixture = transferFixture();
    $staff = User::factory()->staff()->create(['library_id' => $fixture['library']->id]);
    $staff->libraryMemberships()->where('library_id', $fixture['library']->id)->update([
        'branch_id' => $fixture['ownBranch']->id,
    ]);

    Livewire::actingAs($staff)
        ->test(BookCopyForm::class, ['bookCopy' => $fixture['copy']])
        ->set('branchId', $fixture['targetBranch']->id)
        ->call('save')
        ->assertRedirect();

    expect($fixture['copy']->fresh()->branch_id)->toBe($fixture['ownBranch']->id);
});

test('admin can transfer copy inside own library and audit is recorded', function () {
    $fixture = transferFixture();
    $admin = User::factory()->admin()->create(['library_id' => $fixture['library']->id]);

    $this->actingAs($admin)
        ->put(route('manage.book-copies.update', $fixture['copy']), transferPayload(
            $fixture['copy'],
            $fixture['targetBranch'],
            $fixture['targetLocation']
        ))
        ->assertRedirect(route('book-copies.show', $fixture['copy']->id));

    expect($fixture['copy']->fresh()->branch_id)->toBe($fixture['targetBranch']->id);

    $audit = AuditLog::query()->where('action', 'book_copy_updated')->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->metadata['transfer']['old_branch_id'])->toBe($fixture['ownBranch']->id)
        ->and($audit->metadata['transfer']['new_branch_id'])->toBe($fixture['targetBranch']->id)
        ->and($audit->metadata['transfer']['transferred_by'])->toBe($admin->id);
});

test('admin can not transfer copy to another library branch', function () {
    $fixture = transferFixture();
    $admin = User::factory()->admin()->create(['library_id' => $fixture['library']->id]);
    $otherLibrary = Library::factory()->create();
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);
    $otherLocation = Location::factory()->create(['library_id' => $otherLibrary->id, 'branch_id' => $otherBranch->id]);

    $this->actingAs($admin)
        ->from(route('manage.book-copies.edit', $fixture['copy']))
        ->put(route('manage.book-copies.update', $fixture['copy']), transferPayload(
            $fixture['copy'],
            $otherBranch,
            $otherLocation
        ))
        ->assertRedirect(route('manage.book-copies.edit', $fixture['copy']))
        ->assertSessionHasErrors('branch_id');

    expect($fixture['copy']->fresh()->branch_id)->toBe($fixture['ownBranch']->id);
});

test('super admin can not create library mismatch by changing copy library during transfer', function () {
    $fixture = transferFixture();
    $superAdmin = User::factory()->superAdmin()->create();
    $otherLibrary = Library::factory()->create();
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id]);

    $this->actingAs($superAdmin)
        ->from(route('manage.book-copies.edit', $fixture['copy']))
        ->put(route('manage.book-copies.update', $fixture['copy']), transferPayload(
            $fixture['copy'],
            $otherBranch,
            null,
            ['library_id' => $otherLibrary->id]
        ))
        ->assertRedirect(route('manage.book-copies.edit', $fixture['copy']))
        ->assertSessionHasErrors('library_id');

    $fresh = $fixture['copy']->fresh();

    expect($fresh->library_id)->toBe($fixture['library']->id)
        ->and($fresh->branch_id)->toBe($fixture['ownBranch']->id);
});

test('branch dropdown only shows branches valid for the edited copy', function () {
    $fixture = transferFixture();
    $otherLibrary = Library::factory()->create();
    $otherBranch = Branch::factory()->create(['library_id' => $otherLibrary->id, 'name' => 'Svetimas filialas']);
    $admin = User::factory()->admin()->create(['library_id' => $fixture['library']->id]);

    Livewire::actingAs($admin)
        ->test(BookCopyForm::class, ['bookCopy' => $fixture['copy']])
        ->assertSee('Savas filialas')
        ->assertSee('Kitas filialas')
        ->assertDontSee('Svetimas filialas');

    expect($otherBranch->exists)->toBeTrue();
});
