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

function membershipContextCopy(Book $book, Library $library, Branch $branch, string $status = BookCopy::STATUS_AVAILABLE): BookCopy
{
    $location = Location::factory()->create([
        'library_id' => $library->id,
        'branch_id' => $branch->id,
    ]);

    return BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
        'location_id' => $location->id,
        'status' => $status,
    ]);
}

function membershipContextFixture(): array
{
    $libraryX = Library::factory()->create();
    $branchX1 = Branch::factory()->create(['library_id' => $libraryX->id]);
    $branchX2 = Branch::factory()->create(['library_id' => $libraryX->id]);
    $libraryY = Library::factory()->create();
    $branchY1 = Branch::factory()->create(['library_id' => $libraryY->id]);
    $staff = staffInBranch($libraryX, $branchX1, ['email' => 'membership.change@example.test']);
    $member = memberInLibrary($libraryX);
    $book = Book::factory()->create();
    $copyX1 = membershipContextCopy($book, $libraryX, $branchX1);
    $copyX2 = membershipContextCopy($book, $libraryX, $branchX2);
    $copyY1 = membershipContextCopy($book, $libraryY, $branchY1);

    return compact('libraryX', 'branchX1', 'branchX2', 'libraryY', 'branchY1', 'staff', 'member', 'book', 'copyX1', 'copyX2', 'copyY1');
}

function lifecyclePayload(): array
{
    return [
        'target_status' => BookCopy::STATUS_MAINTENANCE,
        'reason_notes' => 'Stale form submit after authorization context changed.',
    ];
}

it('allows staff to view another branch copy but hides management actions', function () {
    $fixture = membershipContextFixture();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('book-copies.show', $fixture['copyX2']))
        ->assertOk()
        ->assertSee($fixture['copyX2']->inventory_code)
        ->assertDontSee('Redaguoti', false)
        ->assertDontSee('Ištrinti', false)
        ->assertDontSee('Gyvenimo ciklas', false);
});

it('blocks staff edit and stale management submits for another branch copy', function () {
    $fixture = membershipContextFixture();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX2']))
        ->assertForbidden();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX2']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX2']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('keeps other library catalog and copies hidden from staff', function () {
    $fixture = membershipContextFixture();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('book-copies.show', $fixture['copyY1']))
        ->assertNotFound();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->getJson("/api/auth/book-copies/{$fixture['copyY1']->id}")
        ->assertNotFound();
});

it('recalculates web permissions after staff moves from branch one to branch two', function () {
    $fixture = membershipContextFixture();
    $membership = $fixture['staff']->membershipForLibrary($fixture['libraryX']->id);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertOk();

    $membership->update(['branch_id' => $fixture['branchX2']->id]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('book-copies.show', $fixture['copyX1']))
        ->assertOk()
        ->assertDontSee('Redaguoti', false);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX1']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX1']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('recalculates web permissions after the active membership is deactivated', function () {
    $fixture = membershipContextFixture();
    $membership = $fixture['staff']->membershipForLibrary($fixture['libraryX']->id);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertOk();

    $membership->update(['is_active' => false]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertForbidden();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX1']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX1']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('recalculates web permissions after membership moves to another library', function () {
    $fixture = membershipContextFixture();
    $membership = $fixture['staff']->membershipForLibrary($fixture['libraryX']->id);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertOk();

    $membership->update([
        'library_id' => $fixture['libraryY']->id,
        'branch_id' => $fixture['branchY1']->id,
        'is_active' => true,
    ]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('book-copies.show', $fixture['copyX1']))
        ->assertNotFound();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX1']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX1']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('recalculates web permissions after global role changes from staff to member', function () {
    $fixture = membershipContextFixture();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertOk();

    $fixture['staff']->forceFill(['role' => User::ROLE_MEMBER])->save();

    $this->actingAs($fixture['staff']->fresh())
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertForbidden();

    $this->actingAs($fixture['staff']->fresh())
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX1']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX1']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('blocks the next web request after the user is globally deactivated', function () {
    $fixture = membershipContextFixture();

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertOk();

    $fixture['staff']->forceFill(['is_active' => false])->save();

    $this->actingAs($fixture['staff']->fresh())
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.book-copies.edit', $fixture['copyX1']))
        ->assertForbidden();

    $this->actingAs($fixture['staff']->fresh())
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->patch(route('manage.book-copies.lifecycle.update', $fixture['copyX1']), lifecyclePayload())
        ->assertForbidden();

    expect($fixture['copyX1']->fresh()->status)->toBe(BookCopy::STATUS_AVAILABLE);
});

it('keeps loans reservations and member operations scoped to the current staff branch', function () {
    $fixture = membershipContextFixture();
    $otherMember = memberInLibrary($fixture['libraryX']);
    $loan = Loan::factory()->create([
        'library_id' => $fixture['libraryX']->id,
        'book_copy_id' => $fixture['copyX2']->id,
        'user_id' => $otherMember->id,
        'status' => Loan::STATUS_ACTIVE,
        'returned_at' => null,
    ]);
    $reservation = Reservation::factory()->create([
        'library_id' => $fixture['libraryX']->id,
        'book_id' => $fixture['book']->id,
        'user_id' => $otherMember->id,
        'scope' => Reservation::SCOPE_BRANCH,
        'branch_id' => $fixture['branchX2']->id,
        'status' => Reservation::STATUS_WAITING,
    ]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->getJson('/api/auth/loans/active')
        ->assertOk()
        ->assertJsonMissing(['id' => $loan->id]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->getJson('/api/auth/reservations')
        ->assertOk()
        ->assertJsonMissing(['id' => $reservation->id]);

    $this->actingAs($fixture['staff'])
        ->withSession(['active_library_id' => $fixture['libraryX']->id])
        ->get(route('manage.users.show', $otherMember))
        ->assertNotFound();
});
