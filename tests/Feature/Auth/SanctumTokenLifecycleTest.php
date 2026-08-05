<?php

use App\Models\Library;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('revokes sanctum tokens when password role or active state changes', function (string $field, mixed $value) {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $user->createToken('android-app');

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $user->forceFill([$field => $value])->save();

    expect(PersonalAccessToken::query()->count())->toBe(0);
})->with([
    ['password', 'new-secret'],
    ['role', User::ROLE_STAFF],
    ['is_active', false],
]);

it('revokes sanctum tokens when membership activity changes', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $token = $user->createToken('android-app')->plainTextToken;
    $membership = $user->activeLibraryMemberships()->firstOrFail();

    $membership->update(['is_active' => false]);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes sanctum tokens when a staff membership branch changes', function () {
    $library = Library::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $library->id]);
    $branchB = Branch::factory()->create(['library_id' => $library->id]);
    $user = staffInBranch($library, $branchA);
    $token = $user->createToken('android-app')->plainTextToken;
    $membership = $user->activeLibraryMemberships()->firstOrFail();

    $membership->update(['branch_id' => $branchB->id]);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->withToken($token)
        ->getJson('/api/auth/books')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes sanctum tokens when a membership moves to another library', function () {
    $libraryA = Library::factory()->create();
    $branchA = Branch::factory()->create(['library_id' => $libraryA->id]);
    $libraryB = Library::factory()->create();
    $branchB = Branch::factory()->create(['library_id' => $libraryB->id]);
    $user = staffInBranch($libraryA, $branchA);
    $token = $user->createToken('android-app')->plainTextToken;
    $membership = $user->activeLibraryMemberships()->firstOrFail();

    $membership->update([
        'library_id' => $libraryB->id,
        'branch_id' => $branchB->id,
    ]);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes sanctum tokens when active membership is replaced by another library', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $libraryA->id]);
    $token = $user->createToken('android-app')->plainTextToken;
    $membershipA = $user->activeLibraryMemberships()->firstOrFail();
    $membershipB = $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => false,
        'joined_at' => now(),
    ]);

    $membershipA->update(['is_active' => false]);
    $membershipB->update(['is_active' => true]);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->withToken($token)
        ->getJson('/api/auth/reservations')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('uses account role as the library authorization authority', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_MEMBER);

    $user->forceFill(['role' => User::ROLE_ADMIN])->save();

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_ADMIN);
});
