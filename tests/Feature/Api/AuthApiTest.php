<?php

use App\Models\Library;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the active library from memberships in the api auth payload', function () {
    $library = Library::factory()->create(['name' => 'Vilniaus miesto centrinė biblioteka']);
    $user = User::factory()->staff()->create(['library_id' => $library->id]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Prisijungta sėkmingai.')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.library_id', $library->id)
        ->assertJsonPath('user.library_name', $library->name)
        ->assertJsonPath('user.role', 'darbuotojas')
        ->assertJsonPath('user.membership_number', $user->membership_number)
        ->assertJsonMissingPath('user.password');
});

it('uses the requested library context for the api me payload', function () {
    $firstLibrary = Library::factory()->create();
    $secondLibrary = Library::factory()->create(['name' => 'Kauno miesto biblioteka']);
    $user = User::factory()->member()->create(['library_id' => $firstLibrary->id]);

    $user->libraryMemberships()->create([
        'library_id' => $secondLibrary->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->withHeader('X-Library-Id', (string) $secondLibrary->id)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.library_id', $secondLibrary->id)
        ->assertJsonPath('user.library_name', $secondLibrary->name)
        ->assertJsonPath('user.role', 'narys');
});

it('does not treat correct credentials as invalid when no memberships are active', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    $user->libraryMemberships()->where('library_id', $library->id)->update(['is_active' => false]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Šiuo metu neturite aktyvios narystės bibliotekoje.');
});

it('allows api login with an inactive membership in one library and an active membership in another', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create(['name' => 'Active Library B']);
    $user = User::factory()->member()->create(['library_id' => $libraryA->id]);

    $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $user->libraryMemberships()->where('library_id', $libraryA->id)->update(['is_active' => false]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('user.library_id', $libraryB->id)
        ->assertJsonPath('user.library_name', $libraryB->name);
});

it('rejects an explicit inactive library context in api requests', function () {
    $libraryA = Library::factory()->create();
    $libraryB = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $libraryA->id]);

    $user->libraryMemberships()->create([
        'library_id' => $libraryB->id,
        'membership_number' => $user->membership_number,
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $user->libraryMemberships()->where('library_id', $libraryA->id)->update(['is_active' => false]);

    $this->actingAs($user)
        ->withHeader('X-Library-Id', (string) $libraryA->id)
        ->getJson('/api/auth/books')
        ->assertForbidden();

    $this->actingAs($user)
        ->withHeader('X-Library-Id', (string) $libraryB->id)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.library_id', $libraryB->id);
});

it('returns the account role to the app', function () {
    $library = Library::factory()->create();
    $user = User::factory()->member()->create(['library_id' => $library->id]);

    $this->actingAs($user)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.role', 'narys');

    $this->actingAs($user)
        ->getJson('/api/auth/member/dashboard')
        ->assertOk();
});
