<?php

use App\Livewire\Manage\Users\UserForm;
use App\Models\Library;
use App\Models\LibraryMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('revokes all api tokens when a user is globally deactivated', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $firstToken = $user->createToken('android-app')->plainTextToken;
    $user->createToken('android-app');

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(2);

    $user->forceFill(['is_active' => false])->save();

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this
        ->withToken($firstToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('handles repeated global deactivation without reviving access', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $user->forceFill(['is_active' => false])->save();
    $user->forceFill(['is_active' => false])->save();

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns consistent unauthenticated responses for revoked tokens on protected api endpoints', function (string $endpoint) {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $user->forceFill(['is_active' => false])->save();

    $this
        ->withToken($token)
        ->getJson($endpoint)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
})->with([
    '/api/auth/me',
    '/api/auth/member/dashboard',
    '/api/auth/dashboard/summary',
    '/api/auth/books',
    '/api/auth/loans/active',
    '/api/auth/reservations',
    '/api/auth/notifications',
]);

it('invalidates a still-present token when an authenticated api user is inactive', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $user->forceFill(['is_active' => false])->saveQuietly();

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'account_inactive')
        ->assertJsonPath('message', 'Account is inactive.');

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('does not mark normal authorization forbidden responses as inactive account responses', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $libraryA->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $this
        ->withToken($token)
        ->withHeader('X-Library-Id', (string) $libraryB->id)
        ->getJson('/api/auth/books')
        ->assertForbidden()
        ->assertJsonMissing(['code' => 'account_inactive']);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(1);
});

it('does not revive old tokens after reactivation and allows login to create a new token', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create([
        'library_id' => $library->id,
        'email' => 'reactivated@example.test',
        'is_active' => true,
    ]);
    $oldToken = $user->createToken('android-app')->plainTextToken;

    $user->forceFill(['is_active' => false])->save();
    $user->forceFill(['is_active' => true])->save();

    $this
        ->withToken($oldToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');

    $this
        ->postJson('/api/auth/login', [
            'email' => 'reactivated@example.test',
            'password' => 'password',
        ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(1);
});

it('revokes tokens when a super admin deactivates through the livewire edit form', function () {
    $library = Library::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    Livewire::actingAs($superAdmin)
        ->test(UserForm::class, ['managedUser' => $user])
        ->set('isActive', false)
        ->call('save')
        ->assertRedirect(route('manage.users.index'));

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes tokens when a super admin deactivates through the management toggle route', function () {
    $library = Library::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $this
        ->actingAs($superAdmin)
        ->patch(route('manage.users.toggle-global-active', $user))
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse()
        ->and(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/auth/reservations')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes tokens when a library membership is deactivated directly', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;
    $membership = $user->libraryMemberships()->where('library_id', $library->id)->firstOrFail();

    $membership->update(['is_active' => false]);

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0);

    $this
        ->withToken($token)
        ->getJson('/api/auth/loans/active')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('revokes tokens when an admin deactivates a member membership from the user list', function () {
    $library = Library::factory()->create();
    $admin = User::factory()->admin()->create(['library_id' => $library->id, 'is_active' => true]);
    $user = User::factory()->member()->create(['library_id' => $library->id, 'is_active' => true]);
    $token = $user->createToken('android-app')->plainTextToken;

    $this
        ->actingAs($admin)
        ->withSession(['active_library_id' => $library->id])
        ->patch(route('manage.users.toggle-membership', $user))
        ->assertRedirect();

    expect(PersonalAccessToken::query()->whereMorphedTo('tokenable', $user)->count())->toBe(0)
        ->and(LibraryMembership::query()
            ->where('library_id', $library->id)
            ->where('user_id', $user->id)
            ->value('is_active'))->toBeFalse();

    $this->app['auth']->forgetGuards();

    $this
        ->withToken($token)
        ->getJson('/api/auth/reservations')
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});
