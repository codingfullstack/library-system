<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps super admin as the global role regardless of membership role', function (?string $membershipRole) {
    $library = Library::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    if ($membershipRole !== null) {
        LibraryMembership::factory()->create([
            'library_id' => $library->id,
            'user_id' => $superAdmin->id,
            'role' => $membershipRole,
        ]);
    }

    expect($superAdmin->fresh()->effectiveRole($library->id))->toBe(User::ROLE_SUPER_ADMIN)
        ->and($superAdmin->fresh()->hasAnyEffectiveRole([User::ROLE_SUPER_ADMIN], $library->id))->toBeTrue();
})->with([
    User::ROLE_MEMBER,
    User::ROLE_STAFF,
    null,
]);

it('resolves active membership role only for the requested library', function (string $role) {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $user->activeLibraryMemberships()->where('library_id', $library->id)->firstOrFail()->update(['role' => $role]);

    expect($user->fresh()->effectiveRole($library->id))->toBe($role)
        ->and($user->fresh()->effectiveRole($otherLibrary->id))->toBeNull()
        ->and($user->fresh()->hasAnyEffectiveRole([$role], $otherLibrary->id))->toBeFalse();
})->with([
    User::ROLE_ADMIN,
    User::ROLE_STAFF,
    User::ROLE_MEMBER,
]);

it('does not infer a non global role without a library context', function () {
    $library = Library::factory()->create();
    $otherLibrary = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    LibraryMembership::factory()->admin()->create([
        'library_id' => $otherLibrary->id,
        'user_id' => $user->id,
    ]);

    expect($user->fresh()->effectiveRole())->toBeNull()
        ->and($user->fresh()->hasAnyEffectiveRole([User::ROLE_ADMIN]))->toBeFalse();
});

it('ignores inactive memberships and inactive users for operational permissions', function () {
    $library = Library::factory()->create();
    $inactiveMembershipUser = User::factory()->member()->create(['library_id' => $library->id]);
    $inactiveMembershipUser->activeLibraryMemberships()->firstOrFail()->update([
        'role' => User::ROLE_ADMIN,
        'is_active' => false,
    ]);

    $inactiveUser = User::factory()->admin()->create(['library_id' => $library->id, 'is_active' => false]);

    expect($inactiveMembershipUser->fresh()->effectiveRole($library->id))->toBeNull()
        ->and($inactiveMembershipUser->fresh()->hasAnyEffectiveRole([User::ROLE_ADMIN], $library->id))->toBeFalse()
        ->and($inactiveUser->fresh()->hasAnyEffectiveRole([User::ROLE_ADMIN], $library->id))->toBeFalse();
});

it('allows staff role without branch but rejects branch-scoped copy management', function () {
    $library = Library::factory()->create();
    $branch = Branch::factory()->create(['library_id' => $library->id]);
    $staff = User::factory()->staff()->create(['library_id' => $library->id]);
    $staff->activeLibraryMemberships()->firstOrFail()->update(['branch_id' => null]);
    $book = Book::factory()->create();
    $copy = BookCopy::factory()->create([
        'library_id' => $library->id,
        'book_id' => $book->id,
        'branch_id' => $branch->id,
    ]);

    expect($staff->fresh()->effectiveRole($library->id))->toBe(User::ROLE_STAFF)
        ->and($staff->fresh()->assignedBranchId($library->id))->toBeNull()
        ->and($staff->fresh()->canManageBookCopy($copy))->toBeFalse();
});
