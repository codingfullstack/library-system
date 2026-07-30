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

it('revokes sanctum tokens when membership activity changes', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);
    $membership = $user->activeLibraryMemberships()->firstOrFail();
    $user->createToken('android-app');

    $membership->update(['is_active' => false]);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('uses account role as the library authorization authority', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_MEMBER);

    $user->forceFill(['role' => User::ROLE_ADMIN])->save();

    expect($user->fresh()->effectiveRole($library->id))->toBe(User::ROLE_ADMIN);
});
