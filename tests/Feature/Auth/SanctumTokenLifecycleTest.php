<?php

use App\Models\Library;
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

it('keeps sanctum tokens when membership activity changes but denies that library context', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $libraryA->id]);
    $membership = $user->activeLibraryMemberships()->firstOrFail();
    $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $user->createToken('android-app');

    $membership->update(['is_active' => false]);

    expect(PersonalAccessToken::query()->count())->toBe(1);

    $this->actingAs($user)
        ->withHeader('X-Library-Id', (string) $libraryA->id)
        ->getJson('/api/auth/me')
        ->assertForbidden();

    $this->actingAs($user)
        ->withHeader('X-Library-Id', (string) $libraryB->id)
        ->getJson('/api/auth/me')
        ->assertOk();
});

it('uses account role as the library authorization authority', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_MEMBER);

    $user->forceFill(['role' => User::ROLE_ADMIN])->save();

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_ADMIN);
});
